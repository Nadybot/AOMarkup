<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A key/value line; the children are the value */
final class Field extends Container implements BlockNode {
	public function __construct(
		public readonly string $name,
		public readonly ?string $unit=null,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
