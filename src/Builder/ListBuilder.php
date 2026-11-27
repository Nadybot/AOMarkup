<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{ListBlock, ListItem, Node};
use Stringable;

/** A list, <ul> or <ol>; takes only items */
final class ListBuilder extends BlockBuilder {
	/** @var list<LiBuilder|ListItem> */
	private array $items = [];

	public function __construct(
		public readonly bool $ordered=false,
		public readonly int $start=1,
	) {
	}

	/** Add items: B::li() */
	public function add(LiBuilder|ListItem ...$items): static {
		array_push($this->items, ...array_values($items));
		return $this;
	}

	/**
	 * Add one item made of these pieces
	 *
	 * Inline pieces next to each other form one paragraph, like B::p(); blocks (e.g. a
	 * nested list) stay blocks. null is left out; without content, no item is added.
	 */
	public function addItem(null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder ...$content): static {
		$content = array_values(array_filter($content, static fn (mixed $piece): bool => $piece !== null));
		return count($content) === 0 ? $this : $this->add(LiBuilder::flow($content));
	}

	public function node(BuildContext $context): ListBlock {
		return new ListBlock($this->ordered, $this->start, ...array_map(
			static fn (LiBuilder|ListItem $item): ListItem => $item instanceof LiBuilder ? $item->item($context) : $item,
			$this->items
		));
	}

	public function build(): ListBlock {
		return $this->node(new BuildContext());
	}
}
