<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Node, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};

/**
 * A length of time in seconds, e.g. <duration seconds="529200"/>
 *
 * Fallback: the duration in the bot's format (RenderContext::$time), e.g.
 * "6 days 3 hrs". It is rendered as it is; no target updates it live.
 */
#[AsWidget('duration')]
final class Duration implements Widget {
	public function __construct(
		public readonly int $seconds,
	) {
	}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Text($ctx->render->time->duration($this->seconds));
	}
}
