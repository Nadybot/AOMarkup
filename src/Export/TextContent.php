<?php declare(strict_types=1);

namespace AO\Markup\Export;

use function Safe\preg_match;

use AO\Markup\Node\{Container, LineBreak, Node, Text};

/** The plain text of inline content, as far as it is known before rendering */
final class TextContent {
	public static function of(Node ...$nodes): string {
		$text = '';
		foreach ($nodes as $node) {
			$text .= match (true) {
				$node instanceof Text => $node->text,
				$node instanceof LineBreak => "\n",
				$node instanceof Container => self::of(...$node->children),
				default => '',
			};
		}
		return $text;
	}

	/** Is the content a number: only digits, "." and ","? Numbers are padded with digits in AOML. */
	public static function isNumeric(Node ...$nodes): bool {
		return preg_match('/^[0-9][0-9.,]*$/', self::of(...$nodes)) === 1;
	}
}
