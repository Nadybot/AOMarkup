<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A layout table (no border) or a data table (border) */
final class Table implements BlockNode {
	/** @var list<TableRow> */
	public readonly array $rows;

	public function __construct(
		public readonly bool $border=false,
		TableRow ...$rows,
	) {
		$this->rows = array_values($rows);
	}

	/**
	 * The effective alignment of a cell
	 *
	 * A cell's own align wins. Otherwise a cell without colspan inherits the
	 * align of the last header cell without colspan above it in the same column.
	 * Everything else is left-aligned.
	 */
	public function alignmentOf(int $rowIndex, int $cellIndex): Align {
		$cell = $this->rows[$rowIndex]->cells[$cellIndex] ?? null;
		if ($cell === null) {
			return Align::Left;
		}
		if ($cell->align !== null) {
			return $cell->align;
		}
		if ($cell->colspan > 1) {
			return Align::Left;
		}
		$column = $this->columnOf($rowIndex, $cellIndex);
		for ($i = $rowIndex - 1; $i >= 0; $i--) {
			foreach ($this->rows[$i]->cells as $j => $above) {
				if (!$above->header || $above->colspan > 1 || $above->align === null) {
					continue;
				}
				if ($this->columnOf($i, $j) === $column) {
					return $above->align;
				}
			}
		}
		return Align::Left;
	}

	/** The 0-based column a cell starts in, taking colspans of the cells before it into account */
	public function columnOf(int $rowIndex, int $cellIndex): int {
		$column = 0;
		foreach (array_slice($this->rows[$rowIndex]->cells ?? [], 0, $cellIndex) as $cell) {
			$column += max(1, $cell->colspan);
		}
		return $column;
	}
}
