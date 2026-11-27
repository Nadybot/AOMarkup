<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{TableCell, TableRow};

/** A table row; takes only cells */
final class TrBuilder implements Builder {
	/** @var list<CellBuilder|TableCell> */
	private array $cells = [];

	public function __construct(CellBuilder|TableCell ...$cells) {
		$this->add(...$cells);
	}

	/** Add cells: B::td(), B::th() */
	public function add(CellBuilder|TableCell ...$cells): static {
		array_push($this->cells, ...array_values($cells));
		return $this;
	}

	public function build(): TableRow {
		return $this->row(new BuildContext());
	}

	/** @internal */
	public function row(BuildContext $context): TableRow {
		return new TableRow(...array_map(
			static fn (CellBuilder|TableCell $cell): TableCell => $cell instanceof CellBuilder ? $cell->cell($context) : $cell,
			$this->cells
		));
	}
}
