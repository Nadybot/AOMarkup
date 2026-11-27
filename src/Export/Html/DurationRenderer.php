<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\NativeRenderer;
use AO\Markup\Widget\Builtin\Duration;
use AO\Markup\Widget\WidgetNode;

/**
 * <duration> as <span data-seconds>, formatted by the bot
 *
 * @implements NativeRenderer<Duration>
 */
final class DurationRenderer implements NativeRenderer {
	public function getWidgetClass(): string {
		return Duration::class;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$duration = $node->widget;
		if (!$duration instanceof Duration) {
			return '';
		}
		return "<span class=\"aom-duration\" data-seconds=\"{$duration->seconds}\">"
			. Html::escape($context->time->duration($duration->seconds)) . '</span>';
	}
}
