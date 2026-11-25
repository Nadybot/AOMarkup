<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A value resolved at export time, e.g. myname, myguild, symbol */
final class Placeholder implements InlineNode {
	public function __construct(
		public readonly string $name,
	) {
	}
}
