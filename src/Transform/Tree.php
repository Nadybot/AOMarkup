<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\{Document, Window};
use AO\Markup\Node\{
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandLink,
	Container,
	Field,
	FieldList,
	FieldValue,
	Footer,
	Fragment,
	Heading,
	Indent,
	ItemLink,
	Keep,
	ListBlock,
	ListItem,
	NanoLink,
	Node,
	Paragraph,
	PopupLink,
	Repeat,
	Section,
	SkillLink,
	Style,
	Table,
	TableCell,
	TableRow,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Widget\WidgetNode;
use Closure;

/** Rebuilds immutable trees */
final class Tree {
	/**
	 * Apply $map to the body and every window of a document
	 *
	 * @param Closure(Node):Node $map
	 */
	public static function mapDocument(Document $document, Closure $map): Document {
		$changed = false;
		$windows = [];
		foreach ($document->windows as $id => $window) {
			$children = self::map($map, $window->children);
			$windows[$id] = $children === $window->children ? $window : new Window($window->id, $window->title, ...$children);
			$changed = $changed || $windows[$id] !== $window;
		}
		$children = self::map($map, $document->body->children);
		if (!$changed && $children === $document->body->children) {
			return $document;
		}
		$body = $children === $document->body->children ? $document->body : new Fragment(...$children);
		return new Document($body, $windows);
	}

	/**
	 * A copy of $node whose content children are replaced by $map($child)
	 *
	 * Structural nodes (fields, field values, table rows and cells, list items)
	 * are not passed to $map, but descended into, so $map sees all content.
	 * If nothing changes, $node itself is returned, so unchanged subtrees keep
	 * their identity (renderers cache measurements of whole tables).
	 *
	 * @param Closure(Node):Node $map
	 */
	public static function mapChildren(Node $node, Closure $map): Node {
		return match (true) {
			$node instanceof FieldList => self::fieldList($node, $map),
			$node instanceof Field => self::field($node, $map),
			$node instanceof ListBlock => self::list($node, $map),
			$node instanceof Table => self::table($node, $map),
			$node instanceof TableRow => self::row($node, $map),
			$node instanceof Container => self::container($node, $map),
			default => $node,
		};
	}

	/** @param Closure(Node):Node $map */
	private static function container(Container $node, Closure $map): Node {
		$c = self::map($map, $node->children);
		if ($c === $node->children) {
			return $node;
		}
		return match (true) {
			$node instanceof Fragment => new Fragment(...$c),
			$node instanceof Paragraph => new Paragraph(...$c),
			$node instanceof Center => new Center(...$c),
			$node instanceof Footer => new Footer(...$c),
			$node instanceof Indent => new Indent(...$c),
			$node instanceof Keep => new Keep(...$c),
			$node instanceof Repeat => new Repeat(...$c),
			$node instanceof Section => new Section($node->title, ...$c),
			$node instanceof Heading => new Heading($node->level, ...$c),
			$node instanceof Window => new Window($node->id, $node->title, ...$c),
			$node instanceof Style => new Style($node->style, ...$c),
			$node instanceof Color => new Color($node->hex, ...$c),
			$node instanceof CharLink => new CharLink($node->name, ...$c),
			$node instanceof ClientCommandLink => new ClientCommandLink($node->command, ...$c),
			$node instanceof CommandLink => new CommandLink($node->command, $node->show, $node->via, ...$c),
			$node instanceof ItemLink => new ItemLink($node->lowId, $node->highId, $node->ql, ...$c),
			$node instanceof NanoLink => new NanoLink($node->id, ...$c),
			$node instanceof SkillLink => new SkillLink($node->id, ...$c),
			$node instanceof PopupLink => new PopupLink($node->window, ...$c),
			$node instanceof UrlLink => new UrlLink($node->href, ...$c),
			$node instanceof WaypointLink => new WaypointLink($node->x, $node->y, $node->playfield, ...$c),
			$node instanceof WidgetNode => new WidgetNode($node->widget, ...$c),
			$node instanceof FieldValue => new FieldValue($node->unit, ...$c),
			$node instanceof ListItem => new ListItem($node->icon, ...$c),
			$node instanceof TableCell => new TableCell($node->header, $node->align, $node->colspan, ...$c),
			default => $node,
		};
	}

	/**
	 * @param Closure(Node):Node $map
	 * @param list<Node>         $nodes
	 *
	 * @return list<Node>
	 */
	private static function map(Closure $map, array $nodes): array {
		return array_map($map, $nodes);
	}

	/**
	 * Map the elements of a typed list of structural nodes
	 *
	 * @template E of Node
	 *
	 * @param list<E>      $nodes
	 * @param Closure(E):E $map
	 *
	 * @return ?list<E> The new list, or null if nothing changed
	 */
	private static function changed(array $nodes, Closure $map): ?array {
		$mapped = array_map($map, $nodes);
		return $mapped === $nodes ? null : $mapped;
	}

	/** @param Closure(Node):Node $map */
	private static function list(ListBlock $list, Closure $map): ListBlock {
		$items = self::changed($list->items, static fn (ListItem $i): ListItem => self::item($i, $map));
		return $items === null ? $list : new ListBlock($list->ordered, $list->start, ...$items);
	}

	/** @param Closure(Node):Node $map */
	private static function item(ListItem $item, Closure $map): ListItem {
		$c = self::map($map, $item->children);
		return $c === $item->children ? $item : new ListItem($item->icon, ...$c);
	}

	/**
	 * Parts of a table keep being parts of the (mapped) whole table
	 *
	 * @param Closure(Node):Node $map
	 */
	private static function table(Table $table, Closure $map): Table {
		$rows = self::changed($table->rows, static fn (TableRow $r): TableRow => self::row($r, $map));
		$whole = $table->layout();
		if ($whole === $table) {
			return $rows === null ? $table : new Table($table->border, ...$rows);
		}
		$mappedWhole = self::table($whole, $map);
		if ($rows === null && $mappedWhole === $whole) {
			return $table;
		}
		return $mappedWhole->part(...$rows ?? $table->rows);
	}

	/** @param Closure(Node):Node $map */
	private static function fieldList(FieldList $list, Closure $map): FieldList {
		$fields = self::changed($list->fields, static fn (Field $f): Field => self::field($f, $map));
		$whole = $list->layout();
		if ($whole === $list) {
			return $fields === null ? $list : new FieldList(...$fields);
		}
		$mappedWhole = self::fieldList($whole, $map);
		if ($fields === null && $mappedWhole === $whole) {
			return $list;
		}
		return $mappedWhole->part(...$fields ?? $list->fields);
	}

	/** @param Closure(Node):Node $map */
	private static function field(Field $field, Closure $map): Field {
		$name = self::map($map, $field->name);
		$values = self::changed($field->values, static fn (FieldValue $v): FieldValue => self::value($v, $map));
		if ($name === $field->name && $values === null) {
			return $field;
		}
		return new Field($name, ...$values ?? $field->values);
	}

	/** @param Closure(Node):Node $map */
	private static function value(FieldValue $value, Closure $map): FieldValue {
		$c = self::map($map, $value->children);
		return $c === $value->children ? $value : new FieldValue($value->unit, ...$c);
	}

	/** @param Closure(Node):Node $map */
	private static function row(TableRow $row, Closure $map): TableRow {
		$cells = self::changed($row->cells, static fn (TableCell $c): TableCell => self::cell($c, $map));
		return $cells === null ? $row : new TableRow(...$cells);
	}

	/** @param Closure(Node):Node $map */
	private static function cell(TableCell $cell, Closure $map): TableCell {
		$c = self::map($map, $cell->children);
		return $c === $cell->children ? $cell : new TableCell($cell->header, $cell->align, $cell->colspan, ...$c);
	}
}
