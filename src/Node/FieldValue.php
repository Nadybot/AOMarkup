<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A value of a field (<dd>); the children are inline nodes, $unit follows the value */
final class FieldValue extends Container {
	public function __construct(
		public readonly ?string $unit=null,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
