<?php declare(strict_types=1);

namespace AO\Markup\Export\Game;

use AO\Markup\Node\StyleName;

/** Budabot markup: named colours closed with <end>, so the receiving bot applies its theme */
final class BbmlStyler implements GameStyler {
	public function style(StyleName $style, string $inner): string {
		$tag = match ($style) {
			StyleName::Strong, StyleName::Syntax => 'highlight',
			StyleName::Match => 'red',
			StyleName::Selected => 'yellow',
			default => $style->value,
		};
		return "<{$tag}>{$inner}<end>";
	}

	public function indent(): string {
		return '<tab>';
	}
}
