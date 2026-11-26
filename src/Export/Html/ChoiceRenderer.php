<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\{NativeRenderer, TextContent};
use AO\Markup\Node\CommandChannel;
use AO\Markup\Widget\Builtin\Choice;
use AO\Markup\Widget\WidgetNode;

/**
 * <choice> as <select>; the web UI sends data-cmd + " " + the chosen value
 *
 * @implements NativeRenderer<Choice>
 */
final class ChoiceRenderer implements NativeRenderer {
	public function getWidgetClass(): string {
		return Choice::class;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$choice = $node->widget;
		if (!$choice instanceof Choice) {
			return '';
		}
		$via = $choice->via === CommandChannel::Tell ? '' : ' data-via="' . $choice->via->value . '"';
		$lines = ['<select class="aom-choice" data-cmd="' . Html::escape($choice->command) . "\"{$via}>"];
		foreach (Choice::options(...$node->children) as [$option, $label]) {
			$lines []= '<option value="' . Html::escape($option->value) . '"' . ($option->selected ? ' selected' : '') . '>'
				. Html::escape(TextContent::of(...$label))
				. '</option>';
		}
		$lines []= '</select>';
		return implode("\n", $lines);
	}
}
