<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class TableRow implements Node {
	/** @var list<TableCell> */
	public readonly array $cells;

	public function __construct(TableCell ...$cells) {
		$this->cells = array_values($cells);
	}

	/** A row consisting only of header cells is repeated on every page */
	public function isHeader(): bool {
		foreach ($this->cells as $cell) {
			if (!$cell->header) {
				return false;
			}
		}
		return count($this->cells) > 0;
	}
}
