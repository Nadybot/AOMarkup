<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{Node, Table, TableCell, TableRow};
use Stringable;

/** A table; takes only rows */
final class TableBuilder extends BlockBuilder {
	/** @var list<TrBuilder|TableRow> */
	private array $rows = [];

	public function __construct(
		public readonly bool $border=false,
	) {
	}

	/** Add rows: B::tr() */
	public function add(TrBuilder|TableRow ...$rows): static {
		array_push($this->rows, ...array_values($rows));
		return $this;
	}

	/**
	 * Add a row of data cells
	 *
	 * A cell builder (B::td(), B::th()) or cell is used as it is, anything else is the
	 * content of a <td>; null is an empty cell.
	 */
	public function addRow(null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder|CellBuilder ...$cells): static {
		return $this->add(self::row(false, $cells));
	}

	/** Add a header row: like addRow(), but content becomes a <th> */
	public function addHeaderRow(null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder|CellBuilder ...$cells): static {
		return $this->add(self::row(true, $cells));
	}

	public function node(BuildContext $context): Table {
		return new Table($this->border, ...array_map(
			static fn (TrBuilder|TableRow $row): TableRow => $row instanceof TrBuilder ? $row->row($context) : $row,
			$this->rows
		));
	}

	public function build(): Table {
		return $this->node(new BuildContext());
	}

	/** @param array<null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder|CellBuilder> $cells */
	private static function row(bool $header, array $cells): TrBuilder {
		$row = new TrBuilder();
		foreach ($cells as $cell) {
			$row->add(match (true) {
				$cell instanceof CellBuilder, $cell instanceof TableCell => $cell,
				default => (new CellBuilder($header))->add($cell),
			});
		}
		return $row;
	}
}
