<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** All named styles of AoXML, semantic ones first */
enum StyleName: string {
	/** Is this a semantic style, or just a raw colour? */
	public function isSemantic(): bool {
		return match ($this) {
			self::Black, self::White, self::Yellow, self::Blue, self::Green,
			self::Red, self::Orange, self::Grey, self::Cyan, self::Violet => false,
			default => true,
		};
	}

	case Header = 'header';
	case Header2 = 'header2';
	case Highlight = 'highlight';
	case On = 'on';
	case Off = 'off';
	case Neutral = 'neutral';
	case Omni = 'omni';
	case Clan = 'clan';
	case Unknown = 'unknown';
	case Match = 'match';
	case Selected = 'selected';
	case Syntax = 'syntax';
	case Underline = 'u';
	case Italic = 'i';
	case Black = 'black';
	case White = 'white';
	case Yellow = 'yellow';
	case Blue = 'blue';
	case Green = 'green';
	case Red = 'red';
	case Orange = 'orange';
	case Grey = 'grey';
	case Cyan = 'cyan';
	case Violet = 'violet';
}
