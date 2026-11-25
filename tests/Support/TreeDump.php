<?php declare(strict_types=1);

namespace AO\Markup\Tests\Support;

use function Safe\json_encode;

use AO\Markup\Document;
use AO\Markup\Node\{Container, Node};
use AO\Markup\Widget\WidgetNode;
use BackedEnum;
use ReflectionObject;
use ReflectionProperty;
use UnitEnum;

/**
 * Readable, indented text form of a document tree, for test assertions
 *
 *   Paragraph
 *     Text "You must set "
 *     Style highlight
 *       Text "73%"
 */
final class TreeDump {
	/** Properties holding child nodes */
	private const CHILD_PROPERTIES = ['children', 'rows', 'cells', 'items', 'fields'];

	public static function document(Document $document): string {
		$lines = [];
		foreach ($document->body->children as $node) {
			self::node($node, 0, $lines);
		}
		foreach ($document->windows as $window) {
			$name = $window->name === null ? '' : ' name=' . json_encode($window->name);
			$lines []= "Window {$window->id} " . json_encode($window->title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $name;
			foreach ($window->children as $node) {
				self::node($node, 1, $lines);
			}
		}
		return implode("\n", $lines);
	}

	public static function nodes(Node ...$nodes): string {
		$lines = [];
		foreach ($nodes as $node) {
			self::node($node, 0, $lines);
		}
		return implode("\n", $lines);
	}

	/** @param list<string> $lines */
	private static function node(Node $node, int $depth, array &$lines): void {
		$class = (new ReflectionObject($node))->getShortName();
		$line = str_repeat('  ', $depth) . $class;
		$subject = $node instanceof WidgetNode ? $node->widget : $node;
		if ($node instanceof WidgetNode) {
			$line .= ' ' . (new ReflectionObject($node->widget))->getShortName();
		}
		foreach (self::scalars($subject) as $name => $value) {
			$line .= ($name === 'text' || $name === 'style') ? " {$value}" : " {$name}={$value}";
		}
		$lines []= $line;
		foreach (self::CHILD_PROPERTIES as $property) {
			if (!property_exists($node, $property)) {
				continue;
			}
			$children = (new ReflectionProperty($node, $property))->getValue($node);
			if (!is_array($children)) {
				continue;
			}
			foreach ($children as $child) {
				if ($child instanceof Node) {
					self::node($child, $depth + 1, $lines);
				}
			}
		}
	}

	/** @return array<string,string> Non-default scalar properties, formatted */
	private static function scalars(object $object): array {
		$result = [];
		$ref = new ReflectionObject($object);
		$defaults = [];
		foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
			if ($param->isDefaultValueAvailable()) {
				$defaults[$param->getName()] = $param->getDefaultValue();
			}
		}
		foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
			$name = $prop->getName();
			if (in_array($name, self::CHILD_PROPERTIES, true) || $object instanceof Container && $name === 'children') {
				continue;
			}
			$value = $prop->getValue($object);
			if (array_key_exists($name, $defaults) && $defaults[$name] === $value) {
				continue;
			}
			$result[$name] = match (true) {
				$value instanceof BackedEnum => (string)$value->value,
				$value instanceof UnitEnum => $value->name,
				$value instanceof Node => trim(self::nodes($value)),
				is_string($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				is_bool($value) => $value ? 'true' : 'false',
				is_int($value), is_float($value) => (string)$value,
				default => get_debug_type($value),
			};
		}
		return $result;
	}
}
