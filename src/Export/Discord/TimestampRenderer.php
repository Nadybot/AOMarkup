<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\NativeRenderer;
use AO\Markup\Widget\Builtin\{Date, Datetime, Time};
use AO\Markup\Widget\WidgetNode;

/**
 * <datetime>, <date> and <time> as Discord timestamps (<t:UNIX:f>, :d, :t)
 *
 * Discord shows them in the reader's own time zone and locale. Register one
 * instance per widget class.
 *
 * @implements NativeRenderer<Datetime|Date|Time>
 */
final class TimestampRenderer implements NativeRenderer {
	/** @param class-string<Datetime|Date|Time> $widgetClass */
	public function __construct(
		private string $widgetClass,
	) {
	}

	public function getWidgetClass(): string {
		return $this->widgetClass;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$widget = $node->widget;
		if (!$widget instanceof Datetime && !$widget instanceof Date && !$widget instanceof Time) {
			return '';
		}
		$style = match (true) {
			$widget instanceof Datetime => 'f',
			$widget instanceof Date => 'd',
			default => 't',
		};
		return "<t:{$widget->value->getTimestamp()}:{$style}>";
	}
}
