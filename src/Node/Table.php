<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A layout table (no border) or a data table (border) */
final class Table implements BlockNode {
	/** @var list<TableRow> */
	public readonly array $rows;

	/** The whole table, if this is a part of it on one page */
	private ?Table $whole = null;

	/** @var ?list<list<Align>> Cache of alignments() */
	private ?array $alignments = null;

	public function __construct(
		public readonly bool $border=false,
		TableRow ...$rows,
	) {
		$this->rows = array_values($rows);
	}

	/**
	 * A part of this table with some of its rows, e.g. for one page
	 *
	 * The part is laid out like the whole table, so the columns have the same
	 * widths on every page.
	 */
	public function part(TableRow ...$rows): self {
		$part = new self($this->border, ...$rows);
		$part->whole = $this->layout();
		return $part;
	}

	/** The table to measure the columns on: the whole table this is a part of, or itself */
	public function layout(): self {
		return $this->whole ?? $this;
	}

	/**
	 * The effective alignment of a cell
	 *
	 * A cell's own align wins. Otherwise a cell without colspan inherits the
	 * align of the last header cell without colspan above it in the same column.
	 * Everything else is left-aligned.
	 */
	public function alignmentOf(int $rowIndex, int $cellIndex): Align {
		return $this->alignments()[$rowIndex][$cellIndex] ?? Align::Left;
	}

	/**
	 * The effective alignment of every cell, by row and cell index (see alignmentOf())
	 *
	 * @return list<list<Align>>
	 */
	public function alignments(): array {
		if (isset($this->alignments)) {
			return $this->alignments;
		}
		$defaults = [];
		$result = [];
		foreach ($this->rows as $row) {
			$aligns = [];
			$column = 0;
			foreach ($row->cells as $cell) {
				$span = max(1, $cell->colspan);
				$aligns []= $cell->align ?? ($span > 1 ? Align::Left : ($defaults[$column] ?? Align::Left));
				if ($cell->header && $span === 1 && $cell->align !== null) {
					$defaults[$column] = $cell->align;
				}
				$column += $span;
			}
			$result []= $aligns;
		}
		return $this->alignments = $result;
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
