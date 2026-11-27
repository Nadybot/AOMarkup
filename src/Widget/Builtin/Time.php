<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Node, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};
use DateTimeImmutable;

/**
 * The time of day of a point in time, e.g. <time value="2026-11-20T14:02:00Z"/>
 *
 * Fallback: the time in the bot's time zone and format (RenderContext::$time).
 */
#[AsWidget('time')]
final class Time implements Widget {
	public function __construct(
		public readonly DateTimeImmutable $value,
	) {
	}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Text($ctx->render->time->time($this->value));
	}
}
