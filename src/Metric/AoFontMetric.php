<?php declare(strict_types=1);

namespace AO\Markup\Metric;

use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Pixel widths of the font of Anarchy Online's popup windows
 *
 * Measured by hand first (from Nadybot's AI_MODULE/Botml/TableRenderer), then confirmed
 * and completed with screenshots of the client (docs/FontCalibration.md, 2026-11-27):
 * every character has a whole number of pixels, and there is no kerning. The client
 * shows Latin-1 only; other characters become "¿". The chat uses a different size.
 */
final class AoFontMetric implements TextMetric {
	/** Width used for characters we have no measurement for */
	public const DEFAULT_WIDTH = 8;

	private const WIDTHS = [
		' ' => 5,
		'0' => 8, '1' => 8, '2' => 8, '3' => 8, '4' => 8, '5' => 8, '6' => 8, '7' => 8, '8' => 8, '9' => 8, '_' => 8,
		'i' => 4, 'ï' => 4, 'í' => 4, 'ì' => 4, 'î' => 4, 'j' => 4, 'l' => 4,
		'f' => 5, 't' => 5,
		'r' => 6,
		'c' => 7, 'ç' => 7, 's' => 7, 'ş' => 7, 'z' => 7,
		'a' => 8, 'á' => 8, 'à' => 8, 'â' => 8, 'ä' => 8, 'å' => 8, 'b' => 8, 'd' => 8, 'e' => 8, 'é' => 8,
		'è' => 8, 'ê' => 8, 'ë' => 8, 'g' => 8, 'h' => 8, 'k' => 8, 'n' => 8, 'ñ' => 8, 'o' => 8, 'ö' => 8,
		'p' => 8, 'q' => 8, 'u' => 8, 'ü' => 8, 'v' => 8, 'x' => 8, 'y' => 8,
		'w' => 11,
		'm' => 13,
		'I' => 5,
		'J' => 6,
		'F' => 7, 'L' => 7,
		'E' => 8, 'É' => 8, 'È' => 8, 'Ê' => 8, 'P' => 8, 'T' => 8, 'Y' => 8, 'ß' => 8,
		'A' => 9, 'Ä' => 9, 'Á' => 9, 'À' => 9, 'Â' => 9, 'Å' => 9, 'B' => 9, 'C' => 9, 'K' => 9, 'R' => 9,
		'S' => 9, 'V' => 9, 'X' => 9, 'Z' => 9,
		'D' => 10, 'G' => 10, 'H' => 10, 'N' => 10, 'Ñ' => 10, 'O' => 10, 'Ó' => 10, 'Ò' => 10, 'Ô' => 10,
		'Ö' => 10, 'Q' => 10, 'U' => 10, 'Ú' => 10, 'Ù' => 10, 'Û' => 10, 'Ü' => 10,
		'M' => 11,
		'W' => 13,
		"'" => 3,
		'!' => 5, '.' => 5, ',' => 5,
		'(' => 6, ')' => 6, '-' => 6, '[' => 6, ']' => 6, '\\' => 6, '|' => 6, ':' => 6, '"' => 6, ';' => 6,
		'/' => 6,
		'?' => 7, '°' => 7, '¹' => 7, '²' => 7, '³' => 7,
		'$' => 8, '*' => 8, '{' => 8, '}' => 8, '`' => 8, '«' => 8, '»' => 8, '€' => 8, '£' => 8,
		'&' => 9,
		'#' => 11, '^' => 11, '+' => 11, '=' => 11, '<' => 11, '>' => 11, '~' => 11, '≈' => 11,
		'@' => 13, '½' => 13, '¼' => 13, '⅓' => 13, '¾' => 13,
		'%' => 14,

		// Latin-1, measured in the client on 2026-11-27
		"\u{00A0}" => 5, '¡' => 5, '·' => 5, 'Ì' => 5, 'Í' => 5, 'Î' => 5, 'Ï' => 5,
		'¦' => 6, "\u{00AD}" => 6,
		'ª' => 7, 'º' => 7, '¿' => 7,
		'¢' => 8, '¤' => 8, '¥' => 8, '§' => 8, '¨' => 8, '¯' => 8, '´' => 8, 'µ' => 8, '¶' => 8, '¸' => 8,
		'Ë' => 8, 'Ý' => 8, 'Þ' => 8, 'ã' => 8, 'ð' => 8, 'ò' => 8, 'ó' => 8, 'ô' => 8, 'õ' => 8, 'ø' => 8, 'ù' => 8, 'ú' => 8, 'û' => 8, 'ý' => 8, 'þ' => 8, 'ÿ' => 8,
		'Ã' => 9, 'Ç' => 9,
		'Ð' => 10, 'Õ' => 10, 'Ø' => 10,
		'¬' => 11, '±' => 11, '×' => 11, '÷' => 11,
		'æ' => 12,
		'©' => 13, '®' => 13, 'Æ' => 13,
	];

	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
	) {
	}

	/** Only the characters we measured; the client shows Latin-1 only */
	public function canDisplay(string $char): bool {
		return isset(self::WIDTHS[$char]);
	}

	public function width(string $text): int {
		$width = 0;
		foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
			$charWidth = self::WIDTHS[$char] ?? null;
			if ($charWidth === null) {
				$this->logger->notice("Character '{char}' has an unknown width in the AO font", ['char' => $char]);
				$charWidth = self::DEFAULT_WIDTH;
			}
			$width += $charWidth;
		}
		return $width;
	}

	public function spaceWidth(): int {
		return self::WIDTHS[' '];
	}

	public function spacesBetween(int $from, int $to): int {
		if ($to <= $from) {
			return 0;
		}
		return (int)round(($to - $from) / $this->spaceWidth());
	}
}
