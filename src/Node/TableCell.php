<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class TableCell extends Container {
	public function __construct(
		public readonly bool $header=false,
		public readonly ?Align $align=null,
		public readonly int $colspan=1,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
