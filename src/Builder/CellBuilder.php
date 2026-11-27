<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{Align, TableCell};

/** A table cell, <td> or <th>; a block container */
final class CellBuilder implements Builder {
	use HasBlocks;

	public function __construct(
		public readonly bool $header=false,
		public readonly ?Align $align=null,
		public readonly int $colspan=1,
	) {
	}

	public function build(): TableCell {
		return $this->cell(new BuildContext());
	}

	/** @internal */
	public function cell(BuildContext $context): TableCell {
		return new TableCell($this->header, $this->align, $this->colspan, ...$this->blocks($context));
	}
}
