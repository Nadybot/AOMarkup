<?php declare(strict_types=1);

namespace AO\Markup\Context;

use AO\Markup\Node\StyleName;

/** The default colours of Nadybot */
class DefaultTheme implements Theme {
	public function colorOf(StyleName $style): ?string {
		return match ($style) {
			StyleName::Header => '#FFFF00',
			StyleName::Header2 => '#FCA712',
			StyleName::Strong, StyleName::Syntax => '#FFFFFF',
			StyleName::On => '#00DE42',
			StyleName::Off => '#FF6666',
			StyleName::Neutral => '#E6E1A6',
			StyleName::Omni => '#00FFFF',
			StyleName::Clan => '#F79410',
			StyleName::Unknown => '#FF0000',
			StyleName::Match => '#FF0000',
			StyleName::Selected => '#FFFF00',
			StyleName::Black => '#000000',
			StyleName::White => '#FFFFFF',
			StyleName::Yellow => '#FFFF00',
			StyleName::Blue => '#8CB5FF',
			StyleName::Green => '#00DE42',
			StyleName::Red => '#FF0000',
			StyleName::Orange => '#FCA712',
			StyleName::Grey => '#C3C3C3',
			StyleName::Cyan => '#00FFFF',
			StyleName::Violet => '#8F00FF',
			StyleName::Underline, StyleName::Italic => null,
		};
	}

	public function windowColor(): string {
		return '#89D2E8';
	}

	public function linkColor(): string {
		return '#219CFF';
	}
}
