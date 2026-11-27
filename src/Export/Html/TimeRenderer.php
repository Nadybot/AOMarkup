<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\NativeRenderer;
use AO\Markup\Widget\Builtin\{Date, Datetime, Time};
use AO\Markup\Widget\WidgetNode;
use DateTimeZone;

/**
 * <datetime>, <date> and <time> as <time datetime="…Z">, formatted by the bot
 *
 * The datetime attribute is in UTC, so a script can show it in the reader's time zone.
 * Register one instance per widget class.
 *
 * @implements NativeRenderer<Datetime|Date|Time>
 */
final class TimeRenderer implements NativeRenderer {
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
		[$class, $text] = match (true) {
			$widget instanceof Datetime => ['aom-datetime', $context->time->dateTime($widget->value)],
			$widget instanceof Date => ['aom-date', $context->time->date($widget->value)],
			default => ['aom-time', $context->time->time($widget->value)],
		};
		$utc = $widget->value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
		return "<time class=\"{$class}\" datetime=\"{$utc}\">" . Html::escape($text) . '</time>';
	}
}
