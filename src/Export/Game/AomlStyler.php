<?php declare(strict_types=1);

namespace AO\Markup\Export\Game;

use AO\Markup\Context\Theme;
use AO\Markup\Node\StyleName;

/** Funcom AOML: colours resolved through the theme */
final class AomlStyler implements GameStyler {
	public function __construct(
		private Theme $theme,
	) {
	}

	public function style(StyleName $style, string $inner): string {
		$color = $this->theme->colorOf($style);
		return isset($color) ? "<font color={$color}>{$inner}</font>" : $inner;
	}

	public function indent(): string {
		return '    ';
	}
}
