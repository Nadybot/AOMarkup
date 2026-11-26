<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Node\{BlockNode, Fragment, Indent, Keep, Node, PageBreak, Paragraph, Repeat};
use AO\Markup\Widget\WidgetNode;

/** Block layout rules shared by the line based exporters */
final class Blocks {
	/**
	 * Unwrap fragments and pagination hints, and collect inline runs into paragraphs
	 *
	 * @param list<Node> $nodes
	 *
	 * @return list<Node>
	 */
	public static function flatten(array $nodes): array {
		$blocks = [];
		$inline = [];
		foreach ($nodes as $node) {
			if ($node instanceof Fragment || $node instanceof Keep || $node instanceof Repeat) {
				$children = self::flatten($node->children);
				if ($node instanceof Fragment && !self::hasBlock($children)) {
					array_push($inline, ...$node->children);
					continue;
				}
				if (count($inline) > 0) {
					$blocks []= new Paragraph(...$inline);
					$inline = [];
				}
				array_push($blocks, ...$children);
				continue;
			}
			if ($node instanceof PageBreak) {
				continue;
			}
			if ($node instanceof BlockNode || $node instanceof WidgetNode) {
				if (count($inline) > 0) {
					$blocks []= new Paragraph(...$inline);
					$inline = [];
				}
				$blocks []= $node;
				continue;
			}
			$inline []= $node;
		}
		if (count($inline) > 0) {
			$blocks []= new Paragraph(...$inline);
		}
		return $blocks;
	}

	/** Is $block separated from the $previous one by an empty line? */
	public static function separated(?Node $previous, Node $block): bool {
		return $previous !== null && !($previous instanceof Paragraph && $block instanceof Indent);
	}

	/** @param list<Node> $nodes */
	private static function hasBlock(array $nodes): bool {
		foreach ($nodes as $node) {
			if ($node instanceof BlockNode) {
				return true;
			}
		}
		return false;
	}
}
