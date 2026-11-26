<?php declare(strict_types=1);

namespace AO\Markup\Export\Text;

use function Safe\preg_replace;

use Closure;

/**
 * 24-bit ANSI colours and OSC 8 hyperlinks
 *
 * Colours are a stack: closing one restores the enclosing colour, and resets
 * at the outermost level.
 */
final class AnsiStyler implements TextStyler {
	/** @var list<string> */
	private array $colors = [];

	public function color(string $hex, Closure $inner): string {
		$this->colors []= $hex;
		$result = self::fg($hex) . $inner();
		array_pop($this->colors);
		$previous = $this->colors[count($this->colors) - 1] ?? null;
		return $result . (isset($previous) ? self::fg($previous) : "\e[0m");
	}

	public function underline(Closure $inner): string {
		return "\e[4m" . $inner() . "\e[24m";
	}

	public function italic(Closure $inner): string {
		return "\e[3m" . $inner() . "\e[23m";
	}

	public function link(?string $url, string $color, Closure $inner): string {
		$text = $this->underline(fn (): string => $this->color($color, $inner));
		return isset($url) ? self::hyperlink($url, $text) : $text;
	}

	public function url(string $url, string $text, bool $textIsUrl): string {
		return $text;
	}

	public function visible(string $text): string {
		return preg_replace(["/\e\\]8;;[^\e]*\e\\\\/", "/\e\\[[0-9;]*m/"], '', $text);
	}

	private static function hyperlink(string $url, string $text): string {
		return "\e]8;;{$url}\e\\{$text}\e]8;;\e\\";
	}

	private static function fg(string $hex): string {
		$rgb = array_map(static fn (string $c): int => (int)hexdec($c), str_split(substr($hex, 1), 2));
		return "\e[38;2;" . implode(';', $rgb) . 'm';
	}
}
