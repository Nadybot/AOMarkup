<?php declare(strict_types=1);

namespace AO\Markup\Export\Text;

use Closure;

/** No styles at all; URLs are written out */
final class PlainStyler implements TextStyler {
	public function color(string $hex, Closure $inner): string {
		return $inner();
	}

	public function underline(Closure $inner): string {
		return $inner();
	}

	public function italic(Closure $inner): string {
		return $inner();
	}

	public function link(?string $url, string $color, Closure $inner): string {
		return $inner();
	}

	public function url(string $url, string $text, bool $textIsUrl): string {
		return $textIsUrl ? $text : "{$text} ({$url})";
	}

	public function visible(string $text): string {
		return $text;
	}
}
