<?php declare(strict_types=1);

namespace AO\Markup\Export\Game;

use AO\Markup\Node\StyleName;

/** How AOML and BBML differ: colours of named styles and indentation */
interface GameStyler {
	/** $inner in the colour of $style; $style is never Underline or Italic */
	public function style(StyleName $style, string $inner): string;

	/** One level of indentation */
	public function indent(): string;
}
