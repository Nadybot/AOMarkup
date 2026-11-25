<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A nano program */
final class NanoLink extends Container implements InlineNode {
	public function __construct(
		public readonly int $id,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
