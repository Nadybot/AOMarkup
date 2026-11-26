<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

use AO\Markup\Context\RenderContext;
use AO\Markup\Export\{NativeRenderer, TextContent};
use AO\Markup\Widget\Builtin\Choice;
use AO\Markup\Widget\WidgetNode;

/**
 * The text part of a <choice>: the options, the selected one marked "(current)"
 *
 * The select menu itself is a message component, see component().
 *
 * @implements NativeRenderer<Choice>
 */
final class ChoiceRenderer implements NativeRenderer {
	/** Discord allows at most 25 options per select menu */
	public const MAX_OPTIONS = 25;

	public function getWidgetClass(): string {
		return Choice::class;
	}

	public function render(WidgetNode $node, RenderContext $context): string {
		$lines = [];
		foreach (Choice::options(...$node->children) as [$option, $label]) {
			$lines []= '**' . Markdown::escape(TextContent::of(...$label)) . '**' . ($option->selected ? ' (current)' : '');
		}
		return implode("\n", $lines);
	}

	/**
	 * The select menu of a choice, in an action row
	 *
	 * @return array<string,mixed>
	 */
	public static function component(WidgetNode $node, RenderContext $context): array {
		$choice = $node->widget;
		$options = [];
		if ($choice instanceof Choice) {
			foreach (Choice::options(...$node->children) as [$option, $label]) {
				$entry = ['label' => TextContent::of(...$label), 'value' => $option->value];
				if ($option->selected) {
					$entry['default'] = true;
				}
				$options []= $entry;
			}
		}
		if (count($options) > self::MAX_OPTIONS) {
			$context->logger->warning('Discord allows only {max} options in a select menu', ['max' => self::MAX_OPTIONS]);
			$options = array_slice($options, 0, self::MAX_OPTIONS);
		}
		return [
			'type' => 1,
			'components' => [[
				'type' => 3,
				'custom_id' => 'aom-choice:' . ($choice instanceof Choice ? $choice->command : ''),
				'options' => $options,
			]],
		];
	}
}
