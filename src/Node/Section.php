<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A titled block, preferred position for page breaks in windows without PageBreak */
final class Section extends Container implements BlockNode {
	public function __construct(
		public readonly string $title,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
