<?php declare(strict_types=1);

namespace AO\Markup\Context;

use AO\Markup\Node\StyleName;

/** Maps styles to colours */
interface Theme {
	/** Colour (#RRGGBB) of a style, or null if the style is not a colour (u, i) */
	public function colorOf(StyleName $style): ?string;

	/** Default text colour of windows */
	public function windowColor(): string;

	/** Colour of links in targets that colour them themselves (ANSI, HTML) */
	public function linkColor(): string;
}
