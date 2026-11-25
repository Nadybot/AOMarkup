<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A heading of level 1 or 2 */
final class Heading extends Container implements BlockNode {
	/** @param 1|2 $level */
	public function __construct(
		public readonly int $level,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
