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
		$windows = [];
		foreach ($document->windows as $id => $window) {
			$windows[$id] = new Window($window->id, $window->title, ...self::map($map, $window->children));
		}
		return new Document(new Fragment(...self::map($map, $document->body->children)), $windows);
	}

	/**
	 * A copy of $node whose content children are replaced by $map($child)
	 *
	 * Structural nodes (fields, field values, table rows and cells, list items)
	 * are not passed to $map, but descended into, so $map sees all content.
	 *
	 * @param Closure(Node):Node $map
	 */
	public static function mapChildren(Node $node, Closure $map): Node {
		$c = static fn (Container $node): array => self::map($map, $node->children);
		return match (true) {
			$node instanceof Fragment => new Fragment(...$c($node)),
			$node instanceof Paragraph => new Paragraph(...$c($node)),
			$node instanceof Center => new Center(...$c($node)),
			$node instanceof Footer => new Footer(...$c($node)),
			$node instanceof Indent => new Indent(...$c($node)),
			$node instanceof Keep => new Keep(...$c($node)),
			$node instanceof Repeat => new Repeat(...$c($node)),
			$node instanceof Section => new Section($node->title, ...$c($node)),
			$node instanceof Heading => new Heading($node->level, ...$c($node)),
			$node instanceof Window => new Window($node->id, $node->title, ...$c($node)),
			$node instanceof Style => new Style($node->style, ...$c($node)),
			$node instanceof Color => new Color($node->hex, ...$c($node)),
			$node instanceof CharLink => new CharLink($node->name, ...$c($node)),
			$node instanceof ClientCommandLink => new ClientCommandLink($node->command, ...$c($node)),
			$node instanceof CommandLink => new CommandLink($node->command, $node->show, $node->via, ...$c($node)),
			$node instanceof ItemLink => new ItemLink($node->lowId, $node->highId, $node->ql, ...$c($node)),
			$node instanceof NanoLink => new NanoLink($node->id, ...$c($node)),
			$node instanceof SkillLink => new SkillLink($node->id, ...$c($node)),
			$node instanceof PopupLink => new PopupLink($node->window, ...$c($node)),
			$node instanceof UrlLink => new UrlLink($node->href, ...$c($node)),
			$node instanceof WaypointLink => new WaypointLink($node->x, $node->y, $node->playfield, ...$c($node)),
			$node instanceof WidgetNode => new WidgetNode($node->widget, ...$c($node)),
			$node instanceof FieldList => new FieldList(
				...array_map(static fn (Field $f): Field => self::field($f, $map), $node->fields)
			),
			$node instanceof Field => self::field($node, $map),
			$node instanceof FieldValue => self::value($node, $map),
			$node instanceof ListBlock => new ListBlock(
				$node->ordered,
				$node->start,
				...array_map(static fn (ListItem $i): ListItem => new ListItem($i->icon, ...$c($i)), $node->items)
			),
			$node instanceof ListItem => new ListItem($node->icon, ...$c($node)),
			$node instanceof Table => new Table(
				$node->border,
				...array_map(static fn (TableRow $r): TableRow => self::row($r, $map), $node->rows)
			),
			$node instanceof TableRow => self::row($node, $map),
			$node instanceof TableCell => self::cell($node, $map),
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

	/** @param Closure(Node):Node $map */
	private static function field(Field $field, Closure $map): Field {
		return new Field(
			self::map($map, $field->name),
			...array_map(static fn (FieldValue $v): FieldValue => self::value($v, $map), $field->values)
		);
	}

	/** @param Closure(Node):Node $map */
	private static function value(FieldValue $value, Closure $map): FieldValue {
		return new FieldValue($value->unit, ...self::map($map, $value->children));
	}

	/** @param Closure(Node):Node $map */
	private static function row(TableRow $row, Closure $map): TableRow {
		return new TableRow(...array_map(static fn (TableCell $c): TableCell => self::cell($c, $map), $row->cells));
	}

	/** @param Closure(Node):Node $map */
	private static function cell(TableCell $cell, Closure $map): TableCell {
		return new TableCell($cell->header, $cell->align, $cell->colspan, ...self::map($map, $cell->children));
	}
}
