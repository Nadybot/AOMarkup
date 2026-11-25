<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A character; without children the name is the link text */
final class CharLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $name,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
