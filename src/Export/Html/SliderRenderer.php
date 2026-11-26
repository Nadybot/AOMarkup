<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\NativeRenderer;
use AO\Markup\Widget\Builtin\Slider;
use AO\Markup\Widget\WidgetNode;

/**
 * <slider> as <meter> with its labels
 *
 * @implements NativeRenderer<Slider>
 */
final class SliderRenderer implements NativeRenderer {
	public function getWidgetClass(): string {
		return Slider::class;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$slider = $node->widget;
		if (!$slider instanceof Slider) {
			return '';
		}
		$label = static fn (?string $text, string $part): string => isset($text)
			? "<span class=\"aom-slider-{$part}\">" . Html::escape($text) . '</span>'
			: '';
		return '<div class="aom-slider">'
			. $label($slider->left, 'left')
			. '<meter min="' . Html::number($slider->min) . '" max="' . Html::number($slider->max)
			. '" value="' . Html::number($slider->pos) . '"></meter>'
			. $label($slider->right, 'right')
			. $label($slider->marker, 'marker')
			. '</div>';
	}
}
