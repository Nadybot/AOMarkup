<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\NativeRenderer;
use AO\Markup\Export\Text\{MonospaceRenderer, PlainStyler};
use AO\Markup\Metric\MonospaceMetric;
use AO\Markup\Node\Fragment;
use AO\Markup\Widget\Builtin\Slider;
use AO\Markup\Widget\{ExpansionContext, WidgetNode};

/**
 * <slider> as a code block with its monospace fallback
 *
 * @implements NativeRenderer<Slider>
 */
final class SliderRenderer implements NativeRenderer {
	public function getWidgetClass(): string {
		return Slider::class;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$metric = new MonospaceMetric();
		$fallback = $node->widget->fallback(new ExpansionContext($context, $metric), ...$node->children);
		$text = (new MonospaceRenderer($context, $metric, new PlainStyler(), '•'))->render(new Document(new Fragment($fallback)));
		return Markdown::codeBlock($text);
	}
}
