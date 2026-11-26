<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

use function Safe\preg_match;

use AO\Markup\Node\{ListBlock, ListItem, Node, Paragraph};
use Closure;

/**
 * Finds lists: lines that start with a bullet or a number (BbmlImporter with detectLists)
 *
 * A list is a run of at least two consecutive lines on the same level that start with the
 * same bullet ("- ", "* " or "• "), or with numbers that count up without gaps ("1. ",
 * "2. ", …). Deeper indented lines right after an item belong to it (continuation lines,
 * nested lists). See docs/AoXML.md §6.2.
 *
 * @internal
 */
final class ListDetector {
	private const BULLET = '/^([-*•]) +/u';
	private const NUMBER = '/^(\d{1,4})\. +/';

	/**
	 * @param Closure(list<BbmlToken>):list<Node> $inline Inline nodes of an item
	 * @param Closure(list<BbmlLine>):list<Node>  $fold   Blocks of the deeper lines of an item
	 */
	public function __construct(
		private Closure $inline,
		private Closure $fold,
	) {
	}

	/**
	 * Replace the lists among the lines of one level by list blocks
	 *
	 * @param list<BbmlLine> $items
	 *
	 * @return list<BbmlLine>
	 */
	public function detect(array $items, int $level): array {
		$result = [];
		$count = count($items);
		for ($i = 0; $i < $count; $i++) {
			$first = self::marker($items[$i], $level);
			if ($first === null) {
				$result []= $items[$i];
				continue;
			}

			// Collect the items: each with its deeper lines
			$entries = [];
			$j = $i;
			$expected = $first;
			while ($j < $count) {
				$marker = self::marker($items[$j], $level);
				if ($marker === null || $marker['kind'] !== $first['kind'] || $marker['value'] !== $expected['value']) {
					break;
				}
				$deeper = [];
				for ($k = $j + 1; $k < $count && $items[$k]->kind !== BbmlLine::BLOCK && $items[$k]->kind !== BbmlLine::BLANK && $items[$k]->level > $level; $k++) {
					$deeper []= $items[$k];
				}
				$entries []= [$marker['rest'], $deeper];
				$j = $k;
				$expected = $marker['kind'] === 'number' ? ['kind' => 'number', 'value' => (string)((int)$marker['value'] + 1)] : $marker;
			}
			if (count($entries) < 2) {
				$result []= $items[$i];
				continue;
			}

			$listItems = [];
			foreach ($entries as [$tokens, $deeper]) {
				$content = ($this->inline)($tokens);
				$listItems []= new ListItem(null, ...[
					...(count($content) > 0 ? [new Paragraph(...$content)] : []),
					...(count($deeper) > 0 ? ($this->fold)($deeper) : []),
				]);
			}
			$ordered = $first['kind'] === 'number';
			$result []= new BbmlLine($level, BbmlLine::BLOCK, new ListBlock($ordered, $ordered ? (int)$first['value'] : 1, ...$listItems));
			$i = $j - 1;
		}
		return $result;
	}

	/**
	 * The list marker a line starts with, and the tokens after it
	 *
	 * @return ?array{kind:string,value:string,rest:list<BbmlToken>}
	 */
	private static function marker(BbmlLine $line, int $level): ?array {
		if ($line->kind !== BbmlLine::LINE || $line->level !== $level || count($line->tokens) === 0) {
			return null;
		}
		$first = $line->tokens[0];
		if ($first->type !== BbmlToken::TEXT) {
			return null;
		}
		foreach (['bullet' => self::BULLET, 'number' => self::NUMBER] as $kind => $pattern) {
			if (preg_match($pattern, $first->text, $match) === 1) {
				$rest = substr($first->text, strlen($match[0] ?? ''));
				$tokens = array_slice($line->tokens, 1);
				if ($rest !== '') {
					array_unshift($tokens, new BbmlToken(BbmlToken::TEXT, $rest));
				}
				return ['kind' => $kind, 'value' => $match[1] ?? '', 'rest' => $tokens];
			}
		}
		return null;
	}
}
