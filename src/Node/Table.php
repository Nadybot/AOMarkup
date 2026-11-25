<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A layout table (no border) or a data table (border) */
final class Table implements BlockNode {
	/** @var list<TableRow> */
	public readonly array $rows;

	/** @param list<Align> $columns Alignment per column */
	public function __construct(
		public readonly bool $border=false,
		public readonly array $columns=[],
		TableRow ...$rows,
	) {
		$this->rows = array_values($rows);
	}
}
