<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** Content in an explicit colour, e.g. from <font color=#123456> */
final class Color extends Container implements InlineNode {
	/** @param string $hex Colour in the form #RRGGBB */
	public function __construct(
		public readonly string $hex,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
