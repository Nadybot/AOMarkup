<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\{Document, Window};
use AO\Markup\Metric\{MonospaceMetric, TextMetric};
use AO\Markup\Node\{
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Container,
	Field,
	FieldList,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	Indent,
	ItemLink,
	Keep,
	LineBreak,
	ListBlock,
	ListItem,
	NanoLink,
	Node,
	PageBreak,
	Paragraph,
	Placeholder,
	PopupLink,
	Repeat,
	Section,
	SkillLink,
	Style,
	Table,
	TableCell,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Widget\{AsWidget, Iso8601, Widget, WidgetNode};
use BackedEnum;
use DateTimeInterface;
use ReflectionClass;

/**
 * Canonical AoXML
 *
 * The canonical form: the message first, then every window; sibling blocks are
 * separated by an empty line, block content is indented with one tab per level,
 * list items, table rows and fields are one line each when their content is a
 * single paragraph. Boolean attributes are written as "true", attributes with
 * their default value are left out.
 *
 * Parsing the result gives the same tree, for every tree the parser produces.
 */
final class AoXmlSerializer extends BaseExporter {
	private const PLACEHOLDERS = ['myname', 'myguild', 'symbol'];

	/** Marks a line break until inline() decides how to write it: BREAK depth BREAK_END */
	private const BREAK = "\x01";
	private const BREAK_END = "\x02";

	public function export(Document $document, RenderContext $context): string {
		$parts = [];
		if (count($document->body->children) > 0) {
			$parts []= $this->blocks($document->body->children, 0);
		}
		foreach ($document->windows as $window) {
			$parts []= $this->window($window);
		}
		return implode("\n\n", $parts) . "\n";
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'aoxml';
	}

	private function window(Window $window): string {
		return $this->container('window', ['id' => $window->id, 'title' => $window->title], $window->children, 0);
	}

	/** @param list<Node> $nodes */
	private function blocks(array $nodes, int $depth): string {
		return implode("\n\n", array_map(fn (Node $node): string => $this->block($node, $depth), $nodes));
	}

	private function block(Node $node, int $depth): string {
		$indent = str_repeat("\t", $depth);
		return match (true) {
			$node instanceof Paragraph => $indent . $this->inline($node->children, $depth),
			$node instanceof Section => $this->container('section', ['title' => $node->title], $node->children, $depth),
			$node instanceof Indent => $this->container('indent', [], $node->children, $depth),
			$node instanceof Center => $this->container('center', [], $node->children, $depth),
			$node instanceof Keep => $this->container('keep', [], $node->children, $depth),
			$node instanceof Repeat => $this->container('repeat', [], $node->children, $depth),
			$node instanceof Heading => $indent . $this->inlineBlock("h{$node->level}", $node->children, $depth),
			$node instanceof Footer => $indent . $this->inlineBlock('footer', $node->children, $depth),
			$node instanceof HorizontalRule => "{$indent}<hr/>",
			$node instanceof PageBreak => "{$indent}<pagebreak/>",
			$node instanceof FieldList => $this->fieldList($node, $depth),
			$node instanceof Table => $this->table($node, $depth),
			$node instanceof ListBlock => $this->list($node, $depth),
			$node instanceof WidgetNode => $indent . $this->widget($node, $depth, true),
			default => $indent . $this->inline([$node], $depth),
		};
	}

	/**
	 * A block element whose content is blocks, always on lines of its own
	 *
	 * @param array<string,?string> $attributes
	 * @param list<Node>            $children
	 */
	private function container(string $name, array $attributes, array $children, int $depth): string {
		$indent = str_repeat("\t", $depth);
		if (count($children) === 0) {
			return $indent . $this->element($name, $attributes, '');
		}
		return $indent . $this->openTag($name, $attributes) . "\n"
			. $this->blocks($children, $depth + 1) . "\n"
			. "{$indent}</{$name}>";
	}

	/**
	 * A block element with inline content: on one line, or on lines of its own if it has line breaks
	 *
	 * @param list<Node> $children
	 */
	private function inlineBlock(string $name, array $children, int $depth): string {
		if (!self::hasLineBreak($children)) {
			return $this->element($name, [], $this->inline($children, $depth));
		}
		$indent = str_repeat("\t", $depth);
		return "<{$name}>\n{$indent}\t" . $this->inline($children, $depth + 1) . "\n{$indent}</{$name}>";
	}

	/** @param list<Node> $nodes */
	private static function hasLineBreak(array $nodes): bool {
		foreach ($nodes as $node) {
			if ($node instanceof LineBreak || ($node instanceof Container && self::hasLineBreak($node->children))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Content of a list item or table cell: inline if it is a single paragraph without line breaks
	 *
	 * @param list<Node> $children
	 */
	private function flowContent(array $children, int $depth): string {
		if (count($children) === 0) {
			return '';
		}
		if (count($children) === 1 && $children[0] instanceof Paragraph && !self::hasLineBreak($children[0]->children)) {
			return $this->inline($children[0]->children, $depth);
		}
		return "\n" . $this->blocks($children, $depth + 1) . "\n" . str_repeat("\t", $depth);
	}

	private function fieldList(FieldList $list, int $depth): string {
		$indent = str_repeat("\t", $depth);
		$lines = [];
		foreach ($list->fields as $field) {
			$lines []= $indent . "\t" . $this->field($field, $depth + 1);
		}
		return "{$indent}<dl>\n" . implode("\n", $lines) . "\n{$indent}</dl>";
	}

	private function field(Field $field, int $depth): string {
		$result = $this->element('dt', [], $this->inline($field->name, $depth));
		foreach ($field->values as $value) {
			$result .= $this->element('dd', ['unit' => $value->unit], $this->inline($value->children, $depth));
		}
		return $result;
	}

	private function table(Table $table, int $depth): string {
		$indent = str_repeat("\t", $depth);
		$lines = [];
		foreach ($table->rows as $row) {
			$cells = array_map(fn (TableCell $cell): string => $this->cell($cell, $depth + 2), $row->cells);
			$multiLine = count(array_filter($cells, static fn (string $cell): bool => str_contains($cell, "\n"))) > 0;
			if (!$multiLine) {
				$cells = array_map(fn (TableCell $cell): string => $this->cell($cell, $depth + 1), $row->cells);
				$lines []= "{$indent}\t<tr>" . implode('', $cells) . '</tr>';
				continue;
			}
			$lines []= "{$indent}\t<tr>\n{$indent}\t\t" . implode("\n{$indent}\t\t", $cells) . "\n{$indent}\t</tr>";
		}
		$open = $this->openTag('table', ['border' => $table->border ? 'true' : null]);
		return "{$indent}{$open}\n" . implode("\n", $lines) . "\n{$indent}</table>";
	}

	private function cell(TableCell $cell, int $depth): string {
		return $this->element($cell->header ? 'th' : 'td', [
			'align' => $cell->align?->value,
			'colspan' => $cell->colspan === 1 ? null : (string)$cell->colspan,
		], $this->flowContent($cell->children, $depth));
	}

	private function list(ListBlock $list, int $depth): string {
		$indent = str_repeat("\t", $depth);
		$name = $list->ordered ? 'ol' : 'ul';
		$lines = [];
		foreach ($list->items as $item) {
			$lines []= "{$indent}\t" . $this->listItem($item, $depth + 1);
		}
		$open = $this->openTag($name, ['start' => ($list->ordered && $list->start !== 1) ? (string)$list->start : null]);
		return "{$indent}{$open}\n" . implode("\n", $lines) . "\n{$indent}</{$name}>";
	}

	private function listItem(ListItem $item, int $depth): string {
		$icon = $item->icon === null ? null : self::imageSource($item->icon);
		return $this->element('li', ['icon' => $icon], $this->flowContent($item->children, $depth));
	}

	// ------------------------------------------------------------------
	// Inline content
	// ------------------------------------------------------------------

	/**
	 * Inline content, e.g. of a paragraph
	 *
	 * A line break is written as a line break in the source where that parses back to the
	 * same tree, and as <br/> where it would not: at the start or end of the content, as
	 * the second of several breaks in a row, and next to a space.
	 *
	 * @param list<Node> $nodes
	 */
	private function inline(array $nodes, int $depth): string {
		return self::resolveBreaks($this->inlineRaw($nodes, $depth));
	}

	/** Decide how to write each line break marker, see inline() */
	private static function resolveBreaks(string $raw): string {
		if (!str_contains($raw, self::BREAK)) {
			return $raw;
		}
		$segments = explode(self::BREAK, $raw);
		$result = $segments[0];
		$hasBefore = self::hasContent($segments[0]);
		$lastWasNewline = false;
		for ($i = 1; $i < count($segments); $i++) {
			[$breakDepth, $after] = explode(self::BREAK_END, $segments[$i], 2);
			$before = $segments[$i-1];
			$hasAfter = false;
			for ($j = $i; $j < count($segments); $j++) {
				$rest = $j === $i ? $after : explode(self::BREAK_END, $segments[$j], 2)[1];
				if (self::hasContent($rest)) {
					$hasAfter = true;
					break;
				}
			}
			$inRun = $i > 1 && !self::hasContent(explode(self::BREAK_END, $before, 2)[1] ?? $before);
			$spaceNext = \Safe\preg_match('/\s$/', self::stripTags(explode(self::BREAK_END, $before, 2)[1] ?? $before)) === 1
				|| \Safe\preg_match('/^\s/', self::stripTags($after)) === 1;
			// A source line break is only emitted with the next content, so no element may close before it
			\Safe\preg_match('/^(?:\s|<[^>]*[^\/]>)*/', $after, $m);
			$closesFirst = str_contains($m[0] ?? '', '</');
			$newline = $hasBefore && $hasAfter && !($inRun && $lastWasNewline) && !$spaceNext && !$closesFirst;
			$result .= $newline ? "\n" . str_repeat("\t", (int)$breakDepth) : '<br/>';
			$lastWasNewline = $newline;
			$result .= $after;
			$hasBefore = $hasBefore || self::hasContent($after);
		}
		return $result;
	}

	/** @param list<Node> $nodes */
	private function inlineRaw(array $nodes, int $depth): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= $this->inlineNode($node, $depth);
		}
		return $result;
	}

	/** Does this AoXML snippet show anything, i.e. text or a void element? */
	private static function hasContent(string $aoxml): bool {
		return trim(self::stripTags($aoxml)) !== '';
	}

	/** Remove opening and closing tags, but keep void elements as content */
	private static function stripTags(string $aoxml): string {
		return \Safe\preg_replace('/<\/?[a-z0-9]+(?:\s[^>]*)?(?<!\/)>/', '', $aoxml);
	}

	private function inlineNode(Node $node, int $depth): string {
		$content = fn (Container $node): string => $this->inlineRaw($node->children, $depth);
		return match (true) {
			$node instanceof Text => self::escape($node->text),
			$node instanceof LineBreak => self::BREAK . $depth . self::BREAK_END,
			$node instanceof Style => $this->element($node->style->value, [], $content($node)),
			$node instanceof Color => $this->element('color', ['value' => $node->hex], $content($node)),
			$node instanceof ItemLink => $this->element('item', [
				'id' => $node->lowId === $node->highId ? "{$node->lowId}" : "{$node->lowId}/{$node->highId}",
				'ql' => (string)$node->ql,
			], $content($node)),
			$node instanceof NanoLink => $this->element('nano', ['id' => (string)$node->id], $content($node)),
			$node instanceof SkillLink => $this->element('skill', ['id' => (string)$node->id], $content($node)),
			$node instanceof CharLink => count($node->children) === 0
				? $this->element('char', [], self::escape($node->name))
				: $this->element('char', ['name' => $node->name], $content($node)),
			$node instanceof CommandLink => $this->command($node, $content($node)),
			$node instanceof ClientCommandLink => count($node->children) === 0
				? $this->element('client', [], self::escape($node->command))
				: $this->element('client', ['command' => $node->command], $content($node)),
			$node instanceof WaypointLink => $this->element('waypoint', [
				'x' => (string)$node->x,
				'y' => (string)$node->y,
				'pf' => (string)$node->playfield,
			], $content($node)),
			$node instanceof UrlLink => $this->element('url', ['href' => $node->href], $content($node)),
			$node instanceof PopupLink => $this->element('popup', ['window' => $node->window], $content($node)),
			$node instanceof Image => '<img' . self::attributes(['src' => self::imageSource($node)]) . '/>',
			$node instanceof Placeholder => in_array($node->name, self::PLACEHOLDERS, true)
				? "<{$node->name}/>"
				: '<var' . self::attributes(['name' => $node->name]) . '/>',
			$node instanceof WidgetNode => $this->widget($node, $depth, false),
			$node instanceof Fragment => $content($node),
			// Block nodes inside a line cannot be expressed; keep their text
			$node instanceof Container => $content($node),
			default => '',
		};
	}

	private function command(CommandLink $link, string $content): string {
		$attributes = [
			'show' => $link->show === CommandDisplay::Command ? null : $link->show->value,
			'via' => $link->via === CommandChannel::Tell ? null : $link->via->value,
		];
		if (count($link->children) === 0) {
			return $this->element('cmd', $attributes, self::escape($link->command));
		}
		return $this->element('cmd', ['command' => $link->command, ...$attributes], $content);
	}

	// ------------------------------------------------------------------
	// Widgets
	// ------------------------------------------------------------------

	private function widget(WidgetNode $node, int $depth, bool $block): string {
		$definition = self::definition($node->widget);
		$name = $definition->name;
		$attributes = self::widgetAttributes($node->widget);
		if ($definition->void) {
			return "<{$name}" . self::attributes($attributes) . '/>';
		}
		if ($definition->only !== null) {
			$indent = str_repeat("\t", $depth);
			$lines = array_map(
				fn (Node $child): string => "{$indent}\t" . self::resolveBreaks($this->inlineNode($child, $depth + 1)),
				$node->children
			);
			return $this->openTag($name, $attributes) . "\n" . implode("\n", $lines) . "\n{$indent}</{$name}>";
		}
		if ($block) {
			return ltrim($this->container($name, $attributes, $node->children, $depth), "\t");
		}
		return $this->element($name, $attributes, $this->inlineRaw($node->children, $depth));
	}

	private static function definition(Widget $widget): AsWidget {
		$attributes = (new ReflectionClass($widget))->getAttributes(AsWidget::class);
		return $attributes[0]->newInstance();
	}

	/**
	 * The constructor parameters of a widget that differ from their default
	 *
	 * @return array<string,?string>
	 */
	private static function widgetAttributes(Widget $widget): array {
		$result = [];
		$ref = new ReflectionClass($widget);
		foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
			$name = $param->getName();
			if (!$ref->hasProperty($name)) {
				continue;
			}
			$value = $ref->getProperty($name)->getValue($widget);
			if ($param->isDefaultValueAvailable() && self::sameValue($param->getDefaultValue(), $value)) {
				continue;
			}
			$attribute = strtolower((string)\Safe\preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
			$result[$attribute] = match (true) {
				$value === null => null,
				is_bool($value) => $value ? 'true' : 'false',
				$value instanceof BackedEnum => (string)$value->value,
				$value instanceof DateTimeInterface => Iso8601::format($value),
				is_int($value), is_float($value), is_string($value) => (string)$value,
				default => null,
			};
		}
		return $result;
	}

	private static function sameValue(mixed $default, mixed $value): bool {
		if ((is_int($default) || is_float($default)) && (is_int($value) || is_float($value))) {
			return (float)$default === (float)$value;
		}
		return $default === $value;
	}

	// ------------------------------------------------------------------

	/** @param array<string,?string> $attributes */
	private function element(string $name, array $attributes, string $content): string {
		return $this->openTag($name, $attributes) . $content . "</{$name}>";
	}

	/** @param array<string,?string> $attributes */
	private function openTag(string $name, array $attributes): string {
		return "<{$name}" . self::attributes($attributes) . '>';
	}

	/** @param array<string,?string> $attributes Attributes with a null value are left out */
	private static function attributes(array $attributes): string {
		$result = '';
		foreach ($attributes as $name => $value) {
			if ($value !== null) {
				$result .= " {$name}=\"" . self::escape($value, true) . '"';
			}
		}
		return $result;
	}

	private static function imageSource(Image $image): string {
		return "{$image->source->value}:{$image->id}";
	}

	private static function escape(string $text, bool $attribute=false): string {
		$text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
		return $attribute ? str_replace('"', '&quot;', $text) : $text;
	}
}
