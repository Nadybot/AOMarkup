<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A link opening the window with the id $window; the children are the link text */
final class PopupLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $window,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
