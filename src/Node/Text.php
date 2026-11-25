<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** Unescaped plain text */
final class Text implements InlineNode {
	public function __construct(
		public readonly string $text,
	) {
	}
}
