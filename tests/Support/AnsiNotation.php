<?php declare(strict_types=1);

namespace AO\Markup\Tests\Support;

use function Safe\preg_match;

use InvalidArgumentException;

/** Converts the readable ANSI notation of tests/Samples into real escape codes */
final class AnsiNotation {
	public static function toEscapes(string $notation): string {
		/** @var list<string> */
		$colors = [];
		$result = '';
		$length = strlen($notation);
		for ($i = 0; $i < $length; $i++) {
			$char = $notation[$i];
			if ($char === '}' && ($notation[$i+1] ?? '') === '}') {
				$result .= '}';
				$i++;
				continue;
			}
			if ($char !== '{') {
				$result .= $char;
				continue;
			}
			if (($notation[$i+1] ?? '') === '{') {
				$result .= '{';
				$i++;
				continue;
			}
			$end = strpos($notation, '}', $i);
			if ($end === false) {
				throw new InvalidArgumentException("Unterminated tag at offset {$i}");
			}
			$tag = substr($notation, $i + 1, $end - $i - 1);
			$i = $end;
			$result .= match (true) {
				$tag === '/' => self::pop($colors),
				$tag === 'u' => "\e[4m",
				$tag === '/u' => "\e[24m",
				$tag === 'i' => "\e[3m",
				$tag === '/i' => "\e[23m",
				$tag === '/link' => "\e]8;;\e\\",
				str_starts_with($tag, 'link ') => "\e]8;;" . substr($tag, 5) . "\e\\",
				preg_match('/^#[0-9A-Fa-f]{6}$/', $tag) === 1 => self::push($colors, $tag),
				default => throw new InvalidArgumentException("Unknown tag {{$tag}}"),
			};
		}
		return $result;
	}

	/** @param list<string> $colors */
	private static function push(array &$colors, string $hex): string {
		$colors []= $hex;
		return self::fg($hex);
	}

	/** @param list<string> $colors */
	private static function pop(array &$colors): string {
		array_pop($colors);
		$previous = $colors[count($colors) - 1] ?? null;
		return isset($previous) ? self::fg($previous) : "\e[0m";
	}

	private static function fg(string $hex): string {
		$rgb = array_map('hexdec', str_split(substr($hex, 1), 2));
		return "\e[38;2;" . implode(';', $rgb) . 'm';
	}
}
