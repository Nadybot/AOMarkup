<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Content;
use AO\Markup\Node\{BlockNode, Fragment, LineBreak, Node, Paragraph, Text};
use AO\Markup\Widget\{AsWidget, WidgetNode};
use ReflectionClass;
use Stringable;

/**
 * Turns builder content into nodes
 *
 * Content is a string, number or Stringable (text, "\n" is a line break), a node, a
 * builder, or null (nothing). A Fragment is replaced by its children.
 *
 * @internal
 */
final class ContentConverter {
	/** @var array<class-string,?AsWidget> */
	private static array $definitions = [];

	/**
	 * Inline content; adjacent texts are joined
	 *
	 * @param list<null|string|int|float|Stringable|Content> $content
	 *
	 * @return list<Node>
	 */
	public static function inline(array $content, BuildContext $context): array {
		$nodes = [];
		foreach ($content as $piece) {
			foreach (self::nodes($piece, $context) as $node) {
				$last = array_pop($nodes);
				if ($node instanceof Text && $last instanceof Text) {
					$nodes []= new Text($last->text . $node->text);
					continue;
				}
				if ($last !== null) {
					$nodes []= $last;
				}
				$nodes []= $node;
			}
		}
		return $nodes;
	}

	/**
	 * Flow content: blocks stay blocks, each run of inline content between them is one paragraph
	 *
	 * @param list<null|string|int|float|Stringable|Content> $content
	 *
	 * @return list<Node>
	 */
	public static function flow(array $content, BuildContext $context): array {
		$blocks = [];
		$inline = [];
		foreach (self::inline($content, $context) as $node) {
			if (!self::isBlock($node)) {
				$inline []= $node;
				continue;
			}
			if (count($inline) > 0) {
				$blocks []= new Paragraph(...$inline);
				$inline = [];
			}
			$blocks []= $node;
		}
		if (count($inline) > 0) {
			$blocks []= new Paragraph(...$inline);
		}
		return $blocks;
	}

	/**
	 * Blocks: every piece of content is one block, inline content becomes a paragraph
	 *
	 * @param list<null|string|int|float|Stringable|Content> $content
	 *
	 * @return list<Node>
	 */
	public static function blocks(array $content, BuildContext $context): array {
		$blocks = [];
		foreach ($content as $piece) {
			array_push($blocks, ...self::flow([$piece], $context));
		}
		return $blocks;
	}

	/** A block node, or a widget that is a block */
	public static function isBlock(Node $node): bool {
		return $node instanceof BlockNode
			|| ($node instanceof WidgetNode && (self::definition($node->widget::class)->block ?? false));
	}

	/**
	 * The #[AsWidget] of a widget class
	 *
	 * @param class-string $class
	 */
	public static function definition(string $class): ?AsWidget {
		if (!array_key_exists($class, self::$definitions)) {
			$attributes = (new ReflectionClass($class))->getAttributes(AsWidget::class);
			self::$definitions[$class] = isset($attributes[0]) ? $attributes[0]->newInstance() : null;
		}
		return self::$definitions[$class];
	}

	/** @return list<Node> */
	public static function text(string $text): array {
		$nodes = [];
		foreach (explode("\n", $text) as $i => $line) {
			if ($i > 0) {
				$nodes []= new LineBreak();
			}
			if ($line !== '') {
				$nodes []= new Text($line);
			}
		}
		return $nodes;
	}

	/** @return list<Node> */
	private static function nodes(null|string|int|float|Stringable|Content $piece, BuildContext $context): array {
		return match (true) {
			$piece === null => [],
			$piece instanceof Fragment => self::inline($piece->children, $context),
			$piece instanceof Node => [$piece],
			$piece instanceof InlineBuilder => $piece->nodes($context),
			$piece instanceof BlockBuilder => [$piece->node($context)],
			// Other Content implementations are not known to the builder
			$piece instanceof Content => [],
			default => self::text((string)$piece),
		};
	}
}
