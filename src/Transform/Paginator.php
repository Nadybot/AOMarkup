<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\{Document, Window};
use AO\Markup\Export\Exporter;
use AO\Markup\Node\{
	Center,
	FieldList,
	Footer,
	Fragment,
	Indent,
	Keep,
	LineBreak,
	ListBlock,
	Node,
	PageBreak,
	Paragraph,
	PopupLink,
	Repeat,
	Section,
	Table,
	Text,
};
use Closure;
use Psr\Log\NullLogger;

/**
 * Splits a document into pages that each fit into $maxLength bytes of the target format
 *
 * Every page is a complete message: the message, with the link to the split
 * window and its title marked with the page number, and one page of that window.
 *
 * Pages are filled as far as possible. If a window contains PageBreaks, pages end only
 * at them (at the last one that still fits); only content between two PageBreaks that
 * does not fit on a page by itself is split further. Otherwise pages end between blocks;
 * a section or <keep> that doesn't fit on the current page starts a new one, and is
 * only split if it doesn't fit on a page by itself. Tables are split between rows
 * (header rows are repeated), lists between items, field lists between fields,
 * paragraphs between lines. <repeat> is put at the top of every page, <footer> at
 * the end of the last one. A split section is continued without its title.
 * See docs/AoXML.md §2.2.
 */
final class Paginator {
	/** Page numbers assumed while filling pages first; the real ones are never longer */
	private const MAX_PAGES = 99;

	/**
	 * @param string $pageFormat sprintf() format for the link text and title of a page:
	 *                           the text, the page number and the number of pages
	 */
	public function __construct(
		private string $pageFormat='%s (Page %d / %d)',
	) {
	}

	/** @return list<Document> */
	public function paginate(Document $document, Exporter $exporter, RenderContext $context, int $maxLength): array {
		// Measure without logging, the same document is rendered many times
		$quiet = new RenderContext($context->placeholders, $context->theme, $context->links, new NullLogger());
		$size = static fn (Document $page): int => strlen($exporter->export($page, $quiet));
		if ($size($document) <= $maxLength) {
			return [$document];
		}

		// Split the largest window; Nadybot can only page one popup per message, too
		$window = null;
		$largest = 0;
		foreach ($document->windows as $candidate) {
			$length = $size(new Document($document->body, [$candidate->id => $candidate]));
			if ($length > $largest) {
				[$window, $largest] = [$candidate, $length];
			}
		}
		if ($window === null) {
			$context->logger->warning('The message is too long, but has no window to split into pages');
			return [$document];
		}

		// Fill pages assuming the longest page numbers, then again with the real number of pages:
		// that never needs more digits, so all pages still fit and are fuller
		$fitsWith = fn (int $assumed): Closure => fn (Node ...$content): bool => $size(
			$this->pageDocument($document, $window, array_values($content), $assumed, $assumed)
		) <= $maxLength;
		if (!$fitsWith(self::MAX_PAGES)()) {
			$context->logger->warning('The message is too long even without the content of window "{id}"', ['id' => $window->id]);
			return [$document];
		}
		$pages = $this->pages($window, $fitsWith(self::MAX_PAGES), $quiet);
		$refined = $this->pages($window, $fitsWith(count($pages)), $context);
		if (strlen((string)count($refined)) <= strlen((string)count($pages))) {
			$pages = $refined;
		} else {
			$pages = $this->pages($window, $fitsWith(self::MAX_PAGES), $context);
		}

		$documents = [];
		foreach ($pages as $i => $content) {
			$documents []= $this->pageDocument($document, $window, $content, $i + 1, count($pages));
		}
		return $documents;
	}

	/**
	 * The content of each page
	 *
	 * @param Closure(Node...):bool $fits Does a page with this content fit?
	 *
	 * @return list<list<Node>>
	 */
	private function pages(Window $window, Closure $fits, RenderContext $context): array {
		[$blocks, $repeat, $footer] = self::analyse($window->children);
		$page = static fn (Node ...$content): array => array_values([...$repeat, ...$content]);
		$pageFits = static fn (Node ...$content): bool => $fits(...$page(...$content));

		$pages = [];
		$current = [];
		while (count($blocks) > 0) {
			$block = $blocks[0];
			if ($pageFits(...$current, ...[$block])) {
				$current []= array_shift($blocks);
				continue;
			}
			$moveWhole = ($block instanceof Section || $block instanceof Keep) && count($current) > 0 && $pageFits($block);
			$split = $moveWhole ? null : $this->split($block, static fn (Node $head): bool => $pageFits(...$current, ...[$head]));
			if ($split !== null) {
				$current []= $split[0];
				$blocks[0] = $split[1];
			} elseif (count($current) === 0) {
				$context->logger->warning('A {class} is too large for a page and cannot be split', ['class' => $block::class]);
				$current []= array_shift($blocks);
			}
			$pages []= $page(...$current);
			$current = [];
		}
		if (isset($footer)) {
			if (!$pageFits(...$current, ...[$footer]) && count($current) > 0) {
				$pages []= $page(...$current);
				$current = [];
			}
			$current []= $footer;
		}
		if (count($current) > 0 || count($pages) === 0) {
			$pages []= $page(...$current);
		}
		return $pages;
	}

	/**
	 * The blocks to distribute, the blocks to repeat on every page, and the footer
	 *
	 * Content between PageBreaks becomes one <keep>, so pages only end at PageBreaks.
	 *
	 * @param list<Node> $children
	 *
	 * @return array{list<Node>,list<Node>,?Footer}
	 */
	private static function analyse(array $children): array {
		$blocks = [];
		$repeat = [];
		$footer = null;
		foreach (self::unwrap($children) as $node) {
			if ($node instanceof Repeat) {
				array_push($repeat, ...$node->children);
			} elseif ($node instanceof Footer) {
				$footer = $node;
			} else {
				$blocks []= $node;
			}
		}
		$hasBreaks = count(array_filter($blocks, static fn (Node $b): bool => $b instanceof PageBreak)) > 0;
		if (!$hasBreaks) {
			return [$blocks, $repeat, $footer];
		}
		$segments = [];
		$segment = [];
		foreach ([...$blocks, new PageBreak()] as $block) {
			if (!$block instanceof PageBreak) {
				$segment []= $block;
				continue;
			}
			if (count($segment) > 0) {
				$segments []= new Keep(...$segment);
			}
			$segment = [];
		}
		return [$segments, $repeat, $footer];
	}

	/**
	 * Unwrap fragments
	 *
	 * @param list<Node> $nodes
	 *
	 * @return list<Node>
	 */
	private static function unwrap(array $nodes): array {
		$result = [];
		foreach ($nodes as $node) {
			if ($node instanceof Fragment) {
				array_push($result, ...self::unwrap($node->children));
			} else {
				$result []= $node;
			}
		}
		return $result;
	}

	/**
	 * Split a block into the largest head that fits and the rest
	 *
	 * @param Closure(Node):bool $fits Does the page fit with this head?
	 *
	 * @return ?array{Node,Node}
	 */
	private function split(Node $block, Closure $fits): ?array {
		return match (true) {
			$block instanceof Table => self::splitTable($block, $fits),
			$block instanceof ListBlock => self::splitList($block, $fits),
			$block instanceof FieldList => self::splitFields($block, $fits),
			$block instanceof Paragraph => self::splitParagraph($block, $fits),
			$block instanceof Section => $this->splitContainer(
				self::unwrap($block->children),
				static fn (Node ...$c): Node => new Section($block->title, ...$c),
				static fn (Node ...$c): Node => new Indent(...$c),
				$fits
			),
			$block instanceof Indent => $this->splitContainer(
				self::unwrap($block->children),
				static fn (Node ...$c): Node => new Indent(...$c),
				static fn (Node ...$c): Node => new Indent(...$c),
				$fits
			),
			$block instanceof Center => $this->splitContainer(
				self::unwrap($block->children),
				static fn (Node ...$c): Node => new Center(...$c),
				static fn (Node ...$c): Node => new Center(...$c),
				$fits
			),
			$block instanceof Keep => $this->splitContainer(
				self::unwrap($block->children),
				static fn (Node ...$c): Node => new Keep(...$c),
				static fn (Node ...$c): Node => new Keep(...$c),
				$fits
			),
			default => null,
		};
	}

	/**
	 * Split the blocks of a container: as many whole blocks as fit, plus the head of the next one
	 *
	 * @param list<Node>            $children
	 * @param Closure(Node...):Node $head     Builds the part on this page
	 * @param Closure(Node...):Node $tail     Builds the part on the next page
	 * @param Closure(Node):bool    $fits
	 *
	 * @return ?array{Node,Node}
	 */
	private function splitContainer(array $children, Closure $head, Closure $tail, Closure $fits): ?array {
		$count = self::largest(count($children), static fn (int $n): bool => $fits($head(...array_slice($children, 0, $n))));
		if ($count === count($children)) {
			return null;
		}
		$whole = array_slice($children, 0, $count);
		$split = $this->split($children[$count], static fn (Node $part): bool => $fits($head(...[...$whole, $part])));
		if ($split !== null) {
			return [$head(...[...$whole, $split[0]]), $tail(...[$split[1], ...array_slice($children, $count + 1)])];
		}
		if ($count === 0) {
			return null;
		}
		return [$head(...$whole), $tail(...array_slice($children, $count))];
	}

	/**
	 * Split between rows; the header rows at the top are repeated
	 *
	 * @param Closure(Node):bool $fits
	 *
	 * @return ?array{Node,Node}
	 */
	private static function splitTable(Table $table, Closure $fits): ?array {
		$header = [];
		$rows = $table->rows;
		while (count($rows) > 0 && $rows[0]->isHeader()) {
			$header []= array_shift($rows);
		}
		$count = self::largest(count($rows), static fn (int $n): bool => $fits($table->part(...$header, ...array_slice($rows, 0, $n))));
		if ($count === 0 || $count === count($rows)) {
			return null;
		}
		return [
			$table->part(...$header, ...array_slice($rows, 0, $count)),
			$table->part(...$header, ...array_slice($rows, $count)),
		];
	}

	/**
	 * @param Closure(Node):bool $fits
	 *
	 * @return ?array{Node,Node}
	 */
	private static function splitList(ListBlock $list, Closure $fits): ?array {
		$items = $list->items;
		$part = static fn (int $from, int $to): ListBlock => new ListBlock($list->ordered, $list->start + $from, ...array_slice($items, $from, $to - $from));
		$count = self::largest(count($items), static fn (int $n): bool => $fits($part(0, $n)));
		if ($count === 0 || $count === count($items)) {
			return null;
		}
		return [$part(0, $count), $part($count, count($items))];
	}

	/**
	 * @param Closure(Node):bool $fits
	 *
	 * @return ?array{Node,Node}
	 */
	private static function splitFields(FieldList $list, Closure $fits): ?array {
		$fields = $list->fields;
		$count = self::largest(count($fields), static fn (int $n): bool => $fits($list->part(...array_slice($fields, 0, $n))));
		if ($count === 0 || $count === count($fields)) {
			return null;
		}
		return [$list->part(...array_slice($fields, 0, $count)), $list->part(...array_slice($fields, $count))];
	}

	/**
	 * Split between the lines of a paragraph
	 *
	 * @param Closure(Node):bool $fits
	 *
	 * @return ?array{Node,Node}
	 */
	private static function splitParagraph(Paragraph $paragraph, Closure $fits): ?array {
		$lines = [[]];
		foreach (self::unwrap($paragraph->children) as $node) {
			if ($node instanceof LineBreak) {
				$lines []= [];
			} else {
				$lines[count($lines) - 1] []= $node;
			}
		}
		$join = static function (array $lines): Paragraph {
			$nodes = [];
			foreach ($lines as $i => $line) {
				if ($i > 0) {
					$nodes []= new LineBreak();
				}
				array_push($nodes, ...$line);
			}
			return new Paragraph(...$nodes);
		};
		$count = self::largest(count($lines), static fn (int $n): bool => $fits($join(array_slice($lines, 0, $n))));
		if ($count === 0 || $count === count($lines)) {
			return null;
		}
		return [$join(array_slice($lines, 0, $count)), $join(array_slice($lines, $count))];
	}

	/**
	 * The largest $n in 0..$max for which $fits($n) is true, assuming it is monotonic
	 *
	 * @param Closure(int):bool $fits
	 */
	private static function largest(int $max, Closure $fits): int {
		[$low, $high] = [0, $max];
		while ($low < $high) {
			$mid = intdiv($low + $high + 1, 2);
			if ($fits($mid)) {
				$low = $mid;
			} else {
				$high = $mid - 1;
			}
		}
		return $low;
	}

	/**
	 * A complete message with one page of $window
	 *
	 * @param list<Node> $content
	 */
	private function pageDocument(Document $document, Window $window, array $content, int $page, int $pages): Document {
		$numbered = fn (string $text): string => sprintf($this->pageFormat, $text, $page, $pages);
		$windows = $document->windows;
		$windows[$window->id] = new Window($window->id, $numbered($window->title), ...$content);
		$label = static function (Node $node) use (&$label, $window, $numbered): Node {
			if ($node instanceof PopupLink && $node->window === $window->id) {
				return new PopupLink($node->window, ...self::numberedLabel($node->children, $numbered));
			}
			return Tree::mapChildren($node, $label);
		};
		return new Document(new Fragment(...array_map($label, $document->body->children)), $windows);
	}

	/**
	 * The label of a popup link with the page number, e.g. "Banlist (Page 1 / 2)"
	 *
	 * The page format is applied to a marker; the text around it is added around the label.
	 *
	 * @param list<Node>             $label
	 * @param Closure(string):string $numbered
	 *
	 * @return list<Node>
	 */
	private static function numberedLabel(array $label, Closure $numbered): array {
		$marker = "\u{E000}";
		[$before, $after] = explode($marker, $numbered($marker), 2) + [1 => ''];
		return [
			...($before !== '' ? [new Text($before)] : []),
			...$label,
			...($after !== '' ? [new Text($after)] : []),
		];
	}
}
