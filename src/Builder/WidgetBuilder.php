<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use AO\Markup\Widget\{Widget, WidgetNode};
use Stringable;

/**
 * Any widget with content
 *
 * The content of a block widget (#[AsWidget(block: true)]) without fixed children is
 * blocks, like in a section; otherwise it is inline.
 */
final class WidgetBuilder extends InlineBuilder {
	/** @var list<null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder> */
	private array $children = [];

	public function __construct(
		public readonly Widget $widget,
		null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder ...$children,
	) {
		$this->add(...$children);
	}

	/** Add content */
	public function add(null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder ...$children): static {
		array_push($this->children, ...array_values($children));
		return $this;
	}

	/** @return list<Node> */
	public function nodes(BuildContext $context): array {
		$definition = Content::definition($this->widget::class);
		$children = $definition !== null && $definition->block && $definition->only === null
			? Content::blocks($this->children, $context)
			: Content::inline($this->children, $context);
		return [new WidgetNode($this->widget, ...$children)];
	}
}
