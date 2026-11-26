<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{CommandChannel, CommandDisplay, CommandLink, LineBreak, Node, Paragraph, Style, StyleName, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget, WidgetNode};

/**
 * A choice between values, e.g. the options of a setting
 *
 * Choosing an option runs "$command <value>". Targets with dropdowns render it
 * natively; the fallback lists the options:
 *
 *   No (current)                           inline: Always [No] Separate
 *   Always [select]
 *   In a separate message [select]
 */
#[AsWidget('choice', void: false, block: true, only: ['option'])]
final class Choice implements Widget {
	public function __construct(
		public readonly string $command,
		public readonly CommandChannel $via=CommandChannel::Tell,
		public readonly bool $inline=false,
	) {
	}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		$nodes = [];
		foreach (self::options(...$children) as [$option, $label]) {
			if (count($nodes) > 0) {
				$nodes []= $this->inline ? new Text(' ') : new LineBreak();
			}
			array_push($nodes, ...$this->inline ? $this->inlineOption($option, $label) : $this->listOption($option, $label));
		}
		return new Paragraph(...$nodes);
	}

	/**
	 * The options among the children, with their labels
	 *
	 * @return list<array{Option,list<Node>}>
	 */
	public static function options(Node ...$children): array {
		$options = [];
		foreach ($children as $child) {
			if ($child instanceof WidgetNode && $child->widget instanceof Option) {
				$options []= [$child->widget, $child->children];
			}
		}
		return $options;
	}

	/** The command that chooses $option */
	public function commandFor(Option $option): string {
		return "{$this->command} {$option->value}";
	}

	/**
	 * @param list<Node> $label
	 *
	 * @return list<Node>
	 */
	private function listOption(Option $option, array $label): array {
		$name = new Style(StyleName::Strong, ...$label);
		if ($option->selected) {
			return [$name, new Text(' (current)')];
		}
		return [
			$name,
			new Text(' ['),
			new CommandLink($this->commandFor($option), CommandDisplay::Command, $this->via, new Text('select')),
			new Text(']'),
		];
	}

	/**
	 * @param list<Node> $label
	 *
	 * @return list<Node>
	 */
	private function inlineOption(Option $option, array $label): array {
		if ($option->selected) {
			return [new Style(StyleName::Selected, ...$label)];
		}
		return [new CommandLink($this->commandFor($option), CommandDisplay::Command, $this->via, ...$label)];
	}
}
