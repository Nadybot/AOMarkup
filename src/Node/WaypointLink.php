<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A waypoint at coordinates of a playfield */
final class WaypointLink extends Container implements InlineNode {
	/** @param int $playfield Numeric playfield id */
	public function __construct(
		public readonly int $x,
		public readonly int $y,
		public readonly int $playfield,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
