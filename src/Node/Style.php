<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** Content with a named (semantic or colour) style */
final class Style extends Container implements InlineNode {
	public function __construct(
		public readonly StyleName $style,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
