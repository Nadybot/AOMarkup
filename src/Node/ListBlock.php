<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** An ordered or unordered list */
final class ListBlock implements BlockNode {
	/** @var list<ListItem> */
	public readonly array $items;

	public function __construct(
		public readonly bool $ordered=false,
		public readonly int $start=1,
		ListItem ...$items,
	) {
		$this->items = array_values($items);
	}
}
