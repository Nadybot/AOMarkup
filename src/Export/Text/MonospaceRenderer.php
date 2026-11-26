<?php declare(strict_types=1);

namespace AO\Markup\Export\Text;

use AO\Markup\Context\{PlaceholderProvider, RenderContext};
use AO\Markup\{Document, Window};
use AO\Markup\Export\{Blocks, TextContent};
use AO\Markup\Metric\TextMetric;
use AO\Markup\Node\{
	Align,
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Container,
	FieldList,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	Indent,
	ItemLink,
	LineBreak,
	ListBlock,
	NanoLink,
	Node,
	Paragraph,
	Placeholder,
	PopupLink,
	Section,
	SkillLink,
	Style,
	StyleName,
	Table,
	TableCell,
	Text,
	UrlLink,
	WaypointLink,
};
use Closure;

/**
 * Lays out a document as lines of monospace text, for the plain text and ANSI exporters
 *
 * One instance renders one document. Expects placeholders to be resolved and
 * widgets to be expanded.
 */
final class MonospaceRenderer {
	private const INDENT = '    ';
	private const RULE_LENGTH = 40;

	public function __construct(
		private RenderContext $context,
		private TextMetric $metric,
		private TextStyler $styler,
		private string $bullet,
	) {
	}

	public function render(Document $document): string {
		$single = self::singlePopup($document);
		if ($single !== null) {
			return implode("\n", $this->window($single)) . "\n";
		}
		$lines = $this->blocks($document->body->children);
		foreach ($document->windows as $window) {
			if (count($lines) > 0) {
				$lines []= '';
			}
			array_push($lines, ...$this->window($window));
		}
		return count($lines) > 0 ? implode("\n", $lines) . "\n" : '';
	}

	/** The window to show instead of a message that is only a link to it */
	private static function singlePopup(Document $document): ?Window {
		$blocks = $document->body->children;
		if (count($blocks) !== 1 || !$blocks[0] instanceof Paragraph) {
			return null;
		}
		$links = array_values(array_filter(
			$blocks[0]->children,
			static fn (Node $node): bool => !$node instanceof Text || trim($node->text) !== ''
		));
		if (count($links) !== 1 || !$links[0] instanceof PopupLink) {
			return null;
		}
		return $document->getWindow($links[0]->window);
	}

	/** @return list<string> */
	private function window(Window $window): array {
		$theme = $this->context->theme;
		$text = $this->styler->color($theme->windowColor(), function () use ($window, $theme): string {
			$lines = [
				$this->color($theme->colorOf(StyleName::Header), static fn (): string => $window->title),
				'',
				...$this->blocks($window->children),
			];
			return implode("\n", $lines);
		});
		return explode("\n", $text);
	}

	/**
	 * Render a sequence of blocks; inline nodes in between form paragraphs
	 *
	 * @param list<Node> $nodes
	 * @param bool       $compact No empty lines between the blocks
	 *
	 * @return list<string>
	 */
	private function blocks(array $nodes, bool $compact=false): array {
		$lines = [];
		$previous = null;
		foreach (Blocks::flatten($nodes) as $block) {
			$rendered = $this->block($block);
			if (count($rendered) === 0) {
				continue;
			}
			if (!$compact && Blocks::separated($previous, $block)) {
				$lines []= '';
			}
			array_push($lines, ...$rendered);
			$previous = $block;
		}
		return $lines;
	}

	/** @return list<string> */
	private function block(Node $node): array {
		$theme = $this->context->theme;
		return match (true) {
			$node instanceof Paragraph => $this->paragraph($node->children),
			$node instanceof Section => [
				$this->color($theme->colorOf(StyleName::Header2), static fn (): string => $node->title),
				...self::indent($this->blocks($node->children)),
			],
			$node instanceof Heading => $this->paragraph([
				new Style($node->level === 1 ? StyleName::Header : StyleName::Header2, ...$node->children),
			]),
			$node instanceof Indent => self::indent($this->blocks($node->children)),
			$node instanceof Center, $node instanceof Footer => $this->blocks($node->children),
			$node instanceof HorizontalRule => [str_repeat('─', self::RULE_LENGTH)],
			$node instanceof ListBlock => $this->list($node),
			$node instanceof FieldList => $this->fieldList($node),
			$node instanceof Table => $this->table($node),
			default => $this->unsupported($node),
		};
	}

	/**
	 * @param list<Node> $children
	 *
	 * @return list<string>
	 */
	private function paragraph(array $children): array {
		$text = $this->inline($children);
		return $text === '' ? [] : explode("\n", $text);
	}

	/** @return list<string> */
	private function unsupported(Node $node): array {
		$this->context->logger->warning('Cannot render {class} as text', ['class' => $node::class]);
		return $node instanceof Container ? $this->blocks($node->children) : [];
	}

	/**
	 * @param list<string> $lines
	 *
	 * @return list<string>
	 */
	private static function indent(array $lines, string $prefix=self::INDENT): array {
		return array_map(static fn (string $line): string => $line === '' ? '' : $prefix . $line, $lines);
	}

	/** @return list<string> */
	private function list(ListBlock $list): array {
		$lines = [];
		$number = $list->start;
		$numberWidth = strlen((string)($list->start + count($list->items) - 1));
		foreach ($list->items as $item) {
			$prefix = ($list->ordered ? str_pad(($number++) . '.', $numberWidth + 1, ' ', \STR_PAD_LEFT) : $this->bullet) . ' ';
			$itemLines = $this->blocks($item->children, compact: true);
			if (count($itemLines) === 0) {
				$lines []= rtrim($prefix);
				continue;
			}
			$lines []= $prefix . array_shift($itemLines);
			array_push($lines, ...self::indent($itemLines, str_repeat(' ', $this->width($prefix))));
		}
		return $lines;
	}

	/** @return list<string> */
	private function fieldList(FieldList $list): array {
		$labels = [];
		$labelWidth = 0;
		$numeric = true;
		$valueWidth = 0;
		foreach ($list->fields as $i => $field) {
			$labels[$i] = $this->inline($field->name) . ':';
			$labelWidth = max($labelWidth, $this->width($labels[$i]));
			foreach ($field->values as $value) {
				$numeric = $numeric && TextContent::isNumeric(...$value->children);
				$valueWidth = max($valueWidth, $this->width(TextContent::of(...$value->children)));
			}
		}
		$labelWidth++;

		$lines = [];
		foreach ($list->fields as $i => $field) {
			if (count($field->values) === 0) {
				$lines []= $labels[$i];
				continue;
			}
			foreach ($field->values as $j => $value) {
				$text = $this->inline([new Style(StyleName::Strong, ...$value->children)]);
				if ($numeric) {
					$text = $this->pad($text, $valueWidth, Align::Right, false);
				}
				if (isset($value->unit)) {
					$text .= " {$value->unit}";
				}
				$head = $j === 0 ? $this->pad($labels[$i], $labelWidth, Align::Left, false) : str_repeat(' ', $labelWidth);
				$valueLines = explode("\n", $text);
				$lines []= $head . array_shift($valueLines);
				array_push($lines, ...self::indent($valueLines, str_repeat(' ', $labelWidth)));
			}
		}
		return $lines;
	}

	/** @return list<string> */
	private function table(Table $table): array {
		$separator = $table->border ? ' │ ' : '  ';
		$separatorWidth = $this->width($separator);

		// Render all cells, measure the columns
		$cells = [];
		$widths = [];
		foreach ($table->rows as $r => $row) {
			$column = 0;
			foreach ($row->cells as $c => $cell) {
				$lines = $this->blocks(self::cellContent($cell), compact: true);
				$width = max([0, ...array_map($this->width(...), $lines)]);
				$span = max(1, $cell->colspan);
				$cells[$r][$c] = [$column, $span, $lines, $table->alignmentOf($r, $c)];
				if ($span === 1) {
					$widths[$column] = max($widths[$column] ?? 0, $width);
				}
				$column += $span;
			}
		}

		// Cells spanning several columns widen the last of them if needed
		foreach ($cells as $row) {
			foreach ($row as [$column, $span, $lines]) {
				if ($span === 1) {
					continue;
				}
				$available = $separatorWidth * ($span - 1);
				for ($i = $column; $i < $column + $span; $i++) {
					$available += $widths[$i] ??= 0;
				}
				$needed = max([0, ...array_map($this->width(...), $lines)]);
				if ($needed > $available) {
					$widths[$column + $span - 1] += $needed - $available;
				}
			}
		}
		ksort($widths);

		$lines = [];
		foreach ($table->rows as $r => $row) {
			$height = max([1, ...array_map(static fn (array $cell): int => count($cell[2]), $cells[$r] ?? [])]);
			for ($l = 0; $l < $height; $l++) {
				$parts = [];
				foreach ($cells[$r] ?? [] as $c => [$column, $span, $cellLines, $align]) {
					$width = $separatorWidth * ($span - 1);
					for ($i = $column; $i < $column + $span; $i++) {
						$width += $widths[$i];
					}
					$last = $c === count($row->cells) - 1;
					$parts []= $this->pad($cellLines[$l] ?? '', $width, $align, $last);
				}
				$lines []= rtrim(implode($separator, $parts), ' ');
			}
			if ($table->border && $row->isHeader() && isset($table->rows[$r + 1]) && !$table->rows[$r + 1]->isHeader()) {
				$lines []= implode('─┼─', array_map(static fn (int $w): string => str_repeat('─', $w), $widths));
			}
		}
		return $lines;
	}

	/**
	 * The blocks of a cell; header cells are emphasised
	 *
	 * @return list<Node>
	 */
	private static function cellContent(TableCell $cell): array {
		if (!$cell->header) {
			return $cell->children;
		}
		return array_map(
			static fn (Node $node): Node => $node instanceof Paragraph
				? new Paragraph(new Style(StyleName::Strong, ...$node->children))
				: $node,
			Blocks::flatten($cell->children)
		);
	}

	/** Pad already styled text to $width; the last cell of a line is not padded on the right */
	private function pad(string $text, int $width, Align $align, bool $last): string {
		$gap = max(0, $width - $this->width($text));
		$left = match ($align) {
			Align::Left => 0,
			Align::Right => $gap,
			Align::Center => intdiv($gap, 2),
		};
		return str_repeat(' ', $left) . $text . ($last ? '' : str_repeat(' ', $gap - $left));
	}

	private function width(string $text): int {
		return $this->metric->width($this->styler->visible($text));
	}

	/** @param list<Node> $nodes */
	private function inline(array $nodes): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= $this->inlineNode($node);
		}
		return $result;
	}

	private function inlineNode(Node $node): string {
		$theme = $this->context->theme;
		$link = $theme->linkColor();
		$children = fn (Container $node, string $default=''): Closure => fn (): string => count($node->children) > 0
			? $this->inline($node->children)
			: $default;
		return match (true) {
			$node instanceof Text => $node->text,
			$node instanceof LineBreak => "\n",
			$node instanceof Fragment => $this->inline($node->children),
			$node instanceof Style => $this->style($node),
			$node instanceof Color => $this->styler->color($node->hex, $children($node)),
			$node instanceof ItemLink => $this->styler->link(
				$this->context->links->itemUrl($node->lowId, $node->highId, $node->ql),
				$link,
				$children($node, "QL {$node->ql}")
			),
			$node instanceof NanoLink => $this->styler->link($this->context->links->nanoUrl($node->id), $link, $children($node)),
			$node instanceof SkillLink => $this->styler->link(null, $link, $children($node)),
			$node instanceof CharLink => $this->styler->link(null, $link, $children($node, $node->name)),
			$node instanceof CommandLink => $this->styler->link(null, $link, $children($node, $this->commandText($node))),
			$node instanceof ClientCommandLink => $this->styler->link(null, $link, $children($node, $node->command)),
			$node instanceof WaypointLink => $this->styler->link(null, $link, $children($node, "{$node->x}x{$node->y}")),
			$node instanceof PopupLink => $this->styler->link(null, $link, $children($node)),
			$node instanceof UrlLink => $this->styler->url(
				$node->href,
				$this->styler->link($node->href, $link, $children($node, $node->href)),
				count($node->children) === 0 || TextContent::of(...$node->children) === $node->href
			),
			$node instanceof Placeholder => $this->placeholder($node->name),
			$node instanceof Image => '',
			default => $this->unsupportedInline($node),
		};
	}

	private function style(Style $node): string {
		$theme = $this->context->theme;
		$inner = fn (): string => $this->inline($node->children);
		return match ($node->style) {
			StyleName::Underline => $this->styler->underline($inner),
			StyleName::Italic => $this->styler->italic($inner),
			StyleName::Match, StyleName::Selected => $this->color($theme->colorOf($node->style), static fn (): string => '[')
				. $inner()
				. $this->color($theme->colorOf($node->style), static fn (): string => ']'),
			StyleName::Syntax => $this->color(
				$theme->colorOf($node->style),
				fn (): string => $this->placeholder(PlaceholderProvider::SYMBOL) . $inner()
			),
			default => $this->color($theme->colorOf($node->style), $inner),
		};
	}

	/** @param Closure():string $inner */
	private function color(?string $hex, Closure $inner): string {
		return isset($hex) ? $this->styler->color($hex, $inner) : $inner();
	}

	/** The command as the user would type it */
	private function commandText(CommandLink $node): string {
		$command = $this->placeholder(PlaceholderProvider::SYMBOL) . $node->command;
		if ($node->show === CommandDisplay::Command) {
			return $command;
		}
		$bot = $this->placeholder(PlaceholderProvider::MY_NAME);
		return match ($node->via) {
			CommandChannel::Tell => "/tell {$bot} {$command}",
			CommandChannel::Priv => "/g {$bot} {$command}",
			CommandChannel::Org => "/o {$command}",
		};
	}

	private function placeholder(string $name): string {
		$value = $this->context->placeholders->get($name);
		if ($value === null) {
			$this->context->logger->warning('No value for the placeholder "{name}"', ['name' => $name]);
		}
		return $value ?? '';
	}

	private function unsupportedInline(Node $node): string {
		$this->context->logger->warning('Cannot render {class} as text', ['class' => $node::class]);
		return $node instanceof Container ? $this->inline($node->children) : '';
	}
}
