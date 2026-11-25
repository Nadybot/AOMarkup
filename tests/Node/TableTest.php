<?php declare(strict_types=1);

namespace AO\Markup\Tests\Node;

use AO\Markup\Node\{Align, Table, TableCell, TableRow, Text};
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase {
	public function testHeaderAlignIsColumnDefault(): void {
		$table = new Table(
			true,
			new TableRow(self::th(), self::th(Align::Right)),
			new TableRow(self::td(), self::td()),
		);
		$this->assertSame(Align::Left, $table->alignmentOf(1, 0));
		$this->assertSame(Align::Right, $table->alignmentOf(1, 1));
		$this->assertSame(Align::Right, $table->alignmentOf(0, 1));
	}

	public function testCellAlignOverridesHeader(): void {
		$table = new Table(
			true,
			new TableRow(self::th(Align::Right)),
			new TableRow(self::td(Align::Center)),
		);
		$this->assertSame(Align::Center, $table->alignmentOf(1, 0));
	}

	public function testColspanCellDoesNotInherit(): void {
		$table = new Table(
			true,
			new TableRow(self::th(), self::th(Align::Right), self::th(Align::Right)),
			new TableRow(self::td(), self::td(colspan: 2)),
		);
		$this->assertSame(Align::Left, $table->alignmentOf(1, 1));
	}

	public function testColspanHeaderSetsNoDefault(): void {
		$table = new Table(
			true,
			new TableRow(self::th(Align::Right, colspan: 2)),
			new TableRow(self::td(), self::td()),
		);
		$this->assertSame(Align::Right, $table->alignmentOf(0, 0));
		$this->assertSame(Align::Left, $table->alignmentOf(1, 0));
		$this->assertSame(Align::Left, $table->alignmentOf(1, 1));
	}

	public function testColumnsAreCountedWithColspan(): void {
		$table = new Table(
			true,
			new TableRow(self::th(), self::th(), self::th(Align::Right)),
			new TableRow(self::td(colspan: 2), self::td()),
		);
		$this->assertSame(2, $table->columnOf(1, 1));
		$this->assertSame(Align::Right, $table->alignmentOf(1, 1));
	}

	public function testLastAlignedHeaderAboveWins(): void {
		$table = new Table(
			true,
			new TableRow(self::th(Align::Right)),
			new TableRow(self::th(Align::Center)),
			new TableRow(self::th()),
			new TableRow(self::td()),
		);
		$this->assertSame(Align::Center, $table->alignmentOf(3, 0));
	}

	public function testTableWithoutHeaderIsLeft(): void {
		$table = new Table(false, new TableRow(self::td(), self::td()));
		$this->assertSame(Align::Left, $table->alignmentOf(0, 1));
	}

	private static function th(?Align $align=null, int $colspan=1): TableCell {
		return new TableCell(true, $align, $colspan, new Text('h'));
	}

	private static function td(?Align $align=null, int $colspan=1): TableCell {
		return new TableCell(false, $align, $colspan, new Text('d'));
	}
}
