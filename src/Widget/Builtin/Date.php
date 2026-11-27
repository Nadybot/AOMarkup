<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Node, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};
use DateTimeImmutable;

/**
 * The date of a point in time, e.g. <date value="2026-11-20T14:02:00Z"/>
 *
 * Fallback: the date in the bot's time zone and format (RenderContext::$time).
 */
#[AsWidget('date')]
final class Date implements Widget {
	public function __construct(
		public readonly DateTimeImmutable $value,
	) {
	}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Text($ctx->render->time->date($this->value));
	}
}
