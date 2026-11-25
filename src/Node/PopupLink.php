<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A link opening the window with the id $ref; the children are the link text */
final class PopupLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $ref,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
