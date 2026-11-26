<?php declare(strict_types=1);

namespace AO\Markup\Export\Game;

use function Safe\preg_replace;

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
	ImageSource,
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

/**
 * Renders a document for the game client, as AOML or BBML
 *
 * Windows become text:// popups. Alignment uses the pixel widths of the
 * metric: padding is rounded to whole spaces, numbers are padded with black
 * zeros. One instance renders one document; placeholders must be resolved
 * and widgets expanded.
 */
final class GameRenderer {
	/** Image used for <hr/>, AOML has no rule */
	private const RULE = '<img src=tdb://id:GFX_GUI_FRIENDLIST_SPLITTER>';

	/** Are we inside a popup? Popups cannot be nested. */
	private bool $inWindow = false;

	/** The document being rendered, for its windows */
	private ?Document $document = null;

	public function __construct(
		private RenderContext $context,
		private TextMetric $metric,
		private GameStyler $styler,
		private string $bullet,
	) {
	}

	public function render(Document $document): string {
		$this->document = $document;
		return implode("\n", $this->blocks($document->body->children));
	}

	/** The popup content of a window, ready to be put into href="text://…" */
	private function window(Window $window): string {
		$this->inWindow = true;
		$content = '<font color=' . $this->context->theme->windowColor() . '>'
			. $this->styler->style(StyleName::Header, self::escape($window->title))
			. "\n\n"
			. implode("\n", $this->blocks($window->children));
		$this->inWindow = false;
		return str_replace('"', '&quot;', $content);
	}

	/**
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
		return match (true) {
			$node instanceof Paragraph => $this->paragraph($node->children),
			$node instanceof Section => [
				$this->styler->style(StyleName::Header2, self::escape($node->title)),
				...$this->indent($this->blocks($node->children)),
			],
			$node instanceof Heading => $this->paragraph([
				new Style($node->level === 1 ? StyleName::Header : StyleName::Header2, ...$node->children),
			]),
			$node instanceof Indent => $this->indent($this->blocks($node->children)),
			$node instanceof Center => self::wrap('<center>', $this->blocks($node->children), '</center>'),
			$node instanceof Footer => $this->blocks($node->children),
			$node instanceof HorizontalRule => [self::RULE],
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
		$this->context->logger->warning('Cannot render {class} for the game', ['class' => $node::class]);
		return $node instanceof Container ? $this->blocks($node->children) : [];
	}

	/**
	 * @param list<string> $lines
	 *
	 * @return list<string>
	 */
	private function indent(array $lines, ?string $prefix=null): array {
		$prefix ??= $this->styler->indent();
		return array_map(static fn (string $line): string => $line === '' ? '' : $prefix . $line, $lines);
	}

	/**
	 * Put $open before the first and $close after the last line
	 *
	 * @param list<string> $lines
	 *
	 * @return list<string>
	 */
	private static function wrap(string $open, array $lines, string $close): array {
		if (count($lines) === 0) {
			return [];
		}
		$lines[0] = $open . $lines[0];
		$lines[count($lines) - 1] .= $close;
		return $lines;
	}

	/** @return list<string> */
	private function list(ListBlock $list): array {
		$lines = [];
		$number = $list->start;
		foreach ($list->items as $item) {
			$prefix = ($list->ordered ? ($number++) . '.' : $this->bullet) . ' ';
			if (isset($item->icon)) {
				$prefix = $this->inlineNode($item->icon) . ' ';
			}
			$itemLines = $this->blocks($item->children, compact: true);
			if (count($itemLines) === 0) {
				$lines []= rtrim($prefix);
				continue;
			}
			$lines []= self::escape($prefix) . array_shift($itemLines);
			array_push($lines, ...$this->indent($itemLines, $this->spaces(0, $this->width(self::escape($prefix)))));
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
				$valueWidth = max($valueWidth, $this->metric->width(TextContent::of(...$value->children)));
			}
		}
		$labelWidth += $this->metric->spaceWidth();

		$lines = [];
		foreach ($list->fields as $i => $field) {
			if (count($field->values) === 0) {
				$lines []= $labels[$i];
				continue;
			}
			foreach ($field->values as $j => $value) {
				$text = $this->inline([new Style(StyleName::Strong, ...$value->children)]);
				$from = $j === 0 ? $this->width($labels[$i]) : 0;
				$to = $labelWidth;
				if ($numeric) {
					[$zeros, $rest] = $this->zeros($valueWidth - $this->width($text));
					$text = $zeros . $text;
					$to += $rest;
				}
				if (isset($value->unit)) {
					$text .= ' ' . self::escape($value->unit);
				}
				$head = ($j === 0 ? $labels[$i] : '') . $this->spaces($from, $to, $j === 0 ? 1 : 0);
				$valueLines = explode("\n", $text);
				$lines []= $head . array_shift($valueLines);
				array_push($lines, ...$this->indent($valueLines, $this->spaces(0, $labelWidth)));
			}
		}
		return $lines;
	}

	/** @return list<string> */
	private function table(Table $table): array {
		$separator = $table->border ? ' | ' : '  ';
		$separatorWidth = $this->metric->width($separator);

		// Render all cells, measure the columns, find the numeric ones
		$cells = [];
		$widths = [];
		$numeric = [];
		foreach ($table->rows as $r => $row) {
			$column = 0;
			foreach ($row->cells as $c => $cell) {
				$lines = $this->blocks(self::cellContent($cell), compact: true);
				$span = max(1, $cell->colspan);
				$cells[$r][$c] = [$column, $span, $lines, $table->alignmentOf($r, $c)];
				if ($span === 1) {
					$widths[$column] = max([$widths[$column] ?? 0, ...array_map($this->width(...), $lines)]);
					if (!$cell->header) {
						$numeric[$column] = ($numeric[$column] ?? true) && TextContent::isNumeric(...$cell->children);
					}
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

		// Where each column starts, in pixels
		$starts = [];
		$x = 0;
		foreach ($widths as $column => $width) {
			$starts[$column] = $x;
			$x += $width + $separatorWidth;
		}

		$lines = [];
		foreach ($table->rows as $r => $row) {
			$height = max([1, ...array_map(static fn (array $cell): int => count($cell[2]), $cells[$r] ?? [])]);
			for ($l = 0; $l < $height; $l++) {
				$line = '';
				$x = 0;
				foreach ($cells[$r] ?? [] as $c => [$column, $span, $cellLines, $align]) {
					$start = $starts[$column];
					$end = $start + $separatorWidth * ($span - 1);
					for ($i = $column; $i < $column + $span; $i++) {
						$end += $widths[$i];
					}
					$text = $cellLines[$l] ?? '';
					$zeros = '';
					$width = $this->width($text);
					if ($span === 1 && $text !== '' && !$row->cells[$c]->header && ($numeric[$column] ?? false)) {
						[$zeros] = $this->zeros($end - $start - $width);
						$width += $this->width($zeros);
					}
					$target = match ($align) {
						Align::Left => $start,
						Align::Right => $end - $width,
						Align::Center => $start + intdiv($end - $start - $width, 2),
					};
					$line .= $this->advance($x, $target);
					$line .= $align === Align::Left ? $text . $zeros : $zeros . $text;
					$x += $width;
					if ($c < count($row->cells) - 1) {
						$line .= $this->advance($x, $end) . $separator;
						$x += $separatorWidth;
					}
				}
				$line = rtrim($line, ' ');
				$lines []= $table->border && $row->isHeader() ? "<u>{$line}</u>" : $line;
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

	/**
	 * Black zeros that fill most of $gap pixels, and the pixels they leave
	 *
	 * @return array{string,int}
	 */
	private function zeros(int $gap): array {
		$digit = $this->metric->width('0');
		$count = $digit > 0 ? intdiv(max(0, $gap), $digit) : 0;
		$zeros = $count > 0 ? $this->styler->style(StyleName::Black, str_repeat('0', $count)) : '';
		return [$zeros, max(0, $gap) - $count * $digit];
	}

	/** Spaces from pixel $x to $to, rounded; moves $x to where they end */
	private function advance(int &$x, int $to): string {
		$count = $this->metric->spacesBetween($x, $to);
		$x += $count * $this->metric->spaceWidth();
		return str_repeat(' ', $count);
	}

	/** Spaces that get from pixel $from to $to, rounded, at least $min */
	private function spaces(int $from, int $to, int $min=0): string {
		return str_repeat(' ', max($min, $this->metric->spacesBetween($from, $to)));
	}

	/** Width of rendered markup in pixels */
	private function width(string $markup): int {
		$text = preg_replace(['/<tab>/', '/<[^>]*>/'], ['    ', ''], $markup);
		return $this->metric->width(html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
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
		$children = fn (Container $node, string $default=''): string => count($node->children) > 0
			? $this->inline($node->children)
			: self::escape($default);
		return match (true) {
			$node instanceof Text => self::escape($node->text),
			$node instanceof LineBreak => "\n",
			$node instanceof Fragment => $this->inline($node->children),
			$node instanceof Style => $this->style($node),
			$node instanceof Color => "<font color={$node->hex}>" . $this->inline($node->children) . '</font>',
			$node instanceof ItemLink => self::link(
				"itemref://{$node->lowId}/{$node->highId}/{$node->ql}",
				$children($node, "QL {$node->ql}")
			),
			$node instanceof NanoLink => self::link("itemid://53019/{$node->id}", $children($node)),
			$node instanceof SkillLink => self::link("skillid://{$node->id}", $children($node)),
			$node instanceof CharLink => self::link("user://{$node->name}", $children($node, $node->name)),
			$node instanceof CommandLink => self::link(
				'chatcmd://' . $this->chatCommand($node),
				$children($node, $this->commandText($node))
			),
			$node instanceof ClientCommandLink => self::link("chatcmd://{$node->command}", $children($node, $node->command)),
			$node instanceof WaypointLink => self::link(
				"chatcmd:///waypoint {$node->x} {$node->y} {$node->playfield}",
				$children($node, "{$node->x}x{$node->y}")
			),
			$node instanceof UrlLink => self::link("chatcmd:///start {$node->href}", $children($node, $node->href)),
			$node instanceof PopupLink => $this->popup($node),
			$node instanceof Image => $node->source === ImageSource::Rdb
				? "<img src=rdb://{$node->id}>"
				: "<img src=tdb://id:{$node->id}>",
			$node instanceof Placeholder => self::escape($this->placeholder($node->name)),
			default => $this->unsupportedInline($node),
		};
	}

	private function popup(PopupLink $node): string {
		$label = $this->inline($node->children);
		$window = $this->document?->getWindow($node->window);
		if ($this->inWindow || $window === null) {
			return $label;
		}
		return '<a href="text://' . $this->window($window) . "\">{$label}</a>";
	}

	private function style(Style $node): string {
		$inner = $this->inline($node->children);
		return match ($node->style) {
			StyleName::Underline => "<u>{$inner}</u>",
			StyleName::Italic => "<i>{$inner}</i>",
			StyleName::Match, StyleName::Selected => $this->styler->style($node->style, '[')
				. $inner
				. $this->styler->style($node->style, ']'),
			StyleName::Syntax => $this->styler->style(
				$node->style,
				self::escape($this->placeholder(PlaceholderProvider::SYMBOL)) . $inner
			),
			default => $this->styler->style($node->style, $inner),
		};
	}

	/** The chat command that sends $node to the bot */
	private function chatCommand(CommandLink $node): string {
		$command = $this->placeholder(PlaceholderProvider::SYMBOL) . $node->command;
		$bot = $this->placeholder(PlaceholderProvider::MY_NAME);
		return match ($node->via) {
			CommandChannel::Tell => "/tell {$bot} {$command}",
			CommandChannel::Priv => "/g {$bot} {$command}",
			CommandChannel::Org => "/o {$command}",
		};
	}

	/** The command as the user would type it */
	private function commandText(CommandLink $node): string {
		return $node->show === CommandDisplay::Command
			? $this->placeholder(PlaceholderProvider::SYMBOL) . $node->command
			: $this->chatCommand($node);
	}

	private static function link(string $href, string $text): string {
		$href = str_replace("'", '&#39;', $href);
		return "<a href='{$href}'>{$text}</a>";
	}

	private static function escape(string $text): string {
		return htmlspecialchars($text, \ENT_NOQUOTES | \ENT_HTML5, 'UTF-8');
	}

	private function placeholder(string $name): string {
		$value = $this->context->placeholders->get($name);
		if ($value === null) {
			$this->context->logger->warning('No value for the placeholder "{name}"', ['name' => $name]);
		}
		return $value ?? '';
	}

	private function unsupportedInline(Node $node): string {
		$this->context->logger->warning('Cannot render {class} for the game', ['class' => $node::class]);
		return $node instanceof Container ? $this->inline($node->children) : '';
	}
}
