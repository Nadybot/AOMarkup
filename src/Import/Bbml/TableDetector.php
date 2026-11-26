<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

use function Safe\{preg_match, preg_split};

use AO\Markup\Export\TextContent;
use AO\Markup\Node\{Align, Node, Paragraph, Repeat, Table, TableCell, TableRow};
use Closure;

/**
 * Finds tables whose columns are separated by "|" (BbmlImporter with detectTables)
 *
 * A table is a run of at least two consecutive lines on the same level with "|" between
 * their columns, at least two of them with the most columns (and at least two columns).
 * A separator is a single "|", plain or in a colour of its own (<highlight>|<end>).
 * Lines with fewer columns span the rest with their last cell. If the run follows a
 * header line whose columns are separated by two or more spaces and a line of "_", "-",
 * "=" or "─" (in a <permheader>, or as two lines), that is the header row; so is a first
 * row that is underlined as a whole (<u>…</u>, as AomlExporter writes it). Columns whose
 * cells are all numbers are right-aligned. See docs/AoXML.md §6.1.
 *
 * @internal
 */
final class TableDetector {
	/** @param Closure(list<BbmlToken>):list<Node> $inline Inline nodes of a cell */
	public function __construct(
		private Closure $inline,
	) {
	}

	/**
	 * Replace the tables among the lines of one level by table blocks
	 *
	 * @param list<BbmlLine> $items
	 *
	 * @return list<BbmlLine>
	 */
	public function detect(array $items, int $level): array {
		$result = [];
		$count = count($items);
		for ($i = 0; $i < $count; $i++) {
			$rows = [];
			$underlined = null;
			for ($j = $i; $j < $count && $this->isRow($items[$j], $level); $j++) {
				$tokens = $items[$j]->tokens;
				// A first row that is underlined as a whole is the header row (what AomlExporter writes)
				if ($j === $i && count($tokens) > 2 && $tokens[0]->isTag('u') && $tokens[count($tokens) - 1]->isClosingTag('u')) {
					$underlined = self::cells(array_slice($tokens, 1, -1));
					continue;
				}
				$rows []= self::cells($tokens);
			}
			$columns = max([0, ...array_map('count', $rows)]);
			$full = count(array_filter($rows, static fn (array $cells): bool => count($cells) === $columns));
			if ($underlined !== null && count($underlined) !== $columns) {
				$underlined = null;
				$full = 0;
			}
			if ($columns < 2 || $full < ($underlined === null ? 2 : 1)) {
				$result []= $items[$i];
				continue;
			}

			// A header line and an underline right before the rows
			$header = $underlined;
			$last = count($result) - 1;
			if ($header !== null) {
				// The first row is the header
			} elseif ($last >= 0 && $result[$last]->node instanceof Repeat) {
				$header = self::header($result[$last]->inner, $columns);
				if ($header !== null) {
					array_pop($result);
				}
			} elseif ($last >= 1) {
				$header = self::header([$result[$last - 1], $result[$last]], $columns);
				if ($header !== null) {
					array_splice($result, -2);
				}
			}
			$result []= new BbmlLine($level, BbmlLine::BLOCK, $this->table($header, $rows, $columns));
			$i = $j - 1;
		}
		return $result;
	}

	/**
	 * Split a line at its "|" separators; a line without separators is one cell
	 *
	 * @param list<BbmlToken> $tokens
	 *
	 * @return list<list<BbmlToken>>
	 */
	public static function cells(array $tokens): array {
		$cells = [[]];
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			// A "|" in a colour of its own: <highlight>|<end>, <font color=…>|</font>
			if (
				$token->type === BbmlToken::TAG && !$token->closing && $token->name !== 'a'
				&& ($tokens[$i + 1] ?? null)?->type === BbmlToken::TEXT && trim($tokens[$i + 1]->text) === '|'
				&& (($tokens[$i + 2] ?? null)?->isTag('end') || ($tokens[$i + 2] ?? null)?->isClosingTag('font'))
			) {
				$cells []= [];
				$i += 2;
				continue;
			}
			if ($token->type !== BbmlToken::TEXT || !str_contains($token->text, '|')) {
				$cells[count($cells) - 1] []= $token;
				continue;
			}
			// A single "|" in text; "||" is not a separator (bars, "||" as text)
			foreach (preg_split('/(?<!\|)\|(?!\|)/', $token->text) as $k => $part) {
				if ($k > 0) {
					$cells []= [];
				}
				if ($part !== '') {
					$cells[count($cells) - 1] []= new BbmlToken(BbmlToken::TEXT, $part);
				}
			}
		}
		return self::withoutEmptyEdges($cells);
	}

	private function isRow(BbmlLine $item, int $level): bool {
		return $item->kind === BbmlLine::LINE && $item->level === $level && count(self::cells($item->tokens)) >= 2;
	}

	/**
	 * @param ?list<list<BbmlToken>>      $header
	 * @param list<list<list<BbmlToken>>> $rows
	 */
	private function table(?array $header, array $rows, int $columns): Table {
		$cells = array_map(fn (array $row): array => array_map($this->inline, $row), $rows);

		// Columns of numbers are right-aligned
		$align = [];
		for ($c = 0; $c < $columns; $c++) {
			$numeric = true;
			foreach ($cells as $row) {
				if (count($row) === $columns) {
					$numeric = $numeric && TextContent::isNumeric(...$row[$c]);
				}
			}
			$align[$c] = $numeric ? Align::Right : null;
		}

		$tableRows = [];
		if (isset($header)) {
			$tableRows []= new TableRow(...array_map(
				fn (array $tokens, int $c): TableCell => self::cell(($this->inline)($tokens), true, $align[$c] ?? null),
				$header,
				array_keys($header)
			));
		}
		foreach ($cells as $row) {
			$tableCells = [];
			foreach ($row as $c => $content) {
				$span = $c === count($row) - 1 ? $columns - $c : 1;
				$tableCells []= self::cell($content, false, isset($header) || $span > 1 ? null : ($align[$c] ?? null), $span);
			}
			$tableRows []= new TableRow(...$tableCells);
		}
		return new Table(true, ...$tableRows);
	}

	/** @param list<Node> $content */
	private static function cell(array $content, bool $header, ?Align $align, int $colspan=1): TableCell {
		return new TableCell($header, $align, $colspan, ...(count($content) > 0 ? [new Paragraph(...$content)] : []));
	}

	/**
	 * The cells of a header line, if the lines are a header line and an underline
	 *
	 * @param list<BbmlLine> $lines
	 *
	 * @return ?list<list<BbmlToken>>
	 */
	private static function header(array $lines, int $columns): ?array {
		$lines = array_values(array_filter($lines, static fn (BbmlLine $l): bool => $l->kind !== BbmlLine::BLANK));
		if (count($lines) !== 2 || $lines[0]->kind !== BbmlLine::LINE || $lines[1]->kind !== BbmlLine::LINE) {
			return null;
		}
		if (preg_match('/^[_\-=─]{3,}$/u', trim(TextContent::of($lines[1]->node))) !== 1) {
			return null;
		}
		$cells = self::splitOnSpaces($lines[0]->tokens);
		return count($cells) === $columns ? $cells : null;
	}

	/**
	 * Split a line at runs of two or more spaces
	 *
	 * @param list<BbmlToken> $tokens
	 *
	 * @return list<list<BbmlToken>>
	 */
	private static function splitOnSpaces(array $tokens): array {
		$cells = [[]];
		foreach ($tokens as $token) {
			if ($token->type !== BbmlToken::TEXT) {
				$cells[count($cells) - 1] []= $token;
				continue;
			}
			foreach (preg_split('/ {2,}/', $token->text) as $k => $part) {
				if ($k > 0) {
					$cells []= [];
				}
				if ($part !== '') {
					$cells[count($cells) - 1] []= new BbmlToken(BbmlToken::TEXT, $part);
				}
			}
		}
		return self::withoutEmptyEdges($cells);
	}

	/**
	 * Drop empty cells before the first and after the last separator ("| a | b |")
	 *
	 * @param list<list<BbmlToken>> $cells
	 *
	 * @return list<list<BbmlToken>>
	 */
	private static function withoutEmptyEdges(array $cells): array {
		$empty = static fn (array $cell): bool => trim(implode('', array_map(
			static fn (BbmlToken $t): string => $t->type === BbmlToken::TEXT ? $t->text : '<>',
			$cell
		))) === '';
		if (count($cells) > 1 && $empty($cells[0])) {
			array_shift($cells);
		}
		if (count($cells) > 1 && $empty($cells[count($cells) - 1])) {
			array_pop($cells);
		}
		return $cells;
	}
}
