<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{CommandChannel, Node};
use AO\Markup\Widget\Builtin\Choice;
use AO\Markup\Widget\WidgetNode;
use Stringable;

/** A choice between values, the <choice> widget; takes only options */
final class ChoiceBuilder extends BlockBuilder {
	/** @var list<OptionBuilder> */
	private array $options = [];

	public function __construct(
		public readonly string $command,
		public readonly CommandChannel $via=CommandChannel::Tell,
		public readonly bool $inline=false,
	) {
	}

	/** Add options: B::option() */
	public function add(OptionBuilder ...$options): static {
		array_push($this->options, ...array_values($options));
		return $this;
	}

	/** Add an option; the label is its content */
	public function addOption(
		string|int $value,
		null|string|int|float|Stringable|Node|InlineBuilder $label,
		bool $selected=false,
	): static {
		return $this->add((new OptionBuilder((string)$value, $selected))->add($label));
	}

	public function node(BuildContext $context): WidgetNode {
		return new WidgetNode(
			new Choice($this->command, $this->via, $this->inline),
			...array_map(static fn (OptionBuilder $option): WidgetNode => $option->option($context), $this->options)
		);
	}

	public function build(): WidgetNode {
		return $this->node(new BuildContext());
	}
}
