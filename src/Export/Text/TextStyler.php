<?php declare(strict_types=1);

namespace AO\Markup\Export\Text;

use Closure;

/**
 * How a monospace target shows styles and links
 *
 * Inner content is passed as a closure, so a styler can track what is open
 * while it is rendered (ANSI has to restore the enclosing colour).
 */
interface TextStyler {
	/** @param Closure():string $inner */
	public function color(string $hex, Closure $inner): string;

	/** @param Closure():string $inner */
	public function underline(Closure $inner): string;

	/** @param Closure():string $inner */
	public function italic(Closure $inner): string;

	/**
	 * A clickable link; $url is null for links that only work in the game
	 *
	 * @param Closure():string $inner
	 */
	public function link(?string $url, string $color, Closure $inner): string;

	/** The text of a URL link; $text is the link text, already styled */
	public function url(string $url, string $text, bool $textIsUrl): string;

	/** $text without any codes, for measuring */
	public function visible(string $text): string;
}
