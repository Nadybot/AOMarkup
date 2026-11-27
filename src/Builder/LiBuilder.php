<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Content;
use AO\Markup\Node\{Image, ListItem};
use Stringable;

/** A list item, <li>; a block container */
final class LiBuilder implements Builder {
	use HasBlocks;

	public function __construct(
		public readonly ?Image $icon=null,
	) {
	}

	public function build(): ListItem {
		return $this->item(new BuildContext());
	}

	/** @internal */
	public function item(BuildContext $context): ListItem {
		return new ListItem($this->icon, ...$this->blocks($context));
	}

	/**
	 * An item of flow content: the blocks, and one paragraph for each run of inline pieces
	 *
	 * @internal
	 *
	 * @param list<null|string|int|float|Stringable|Content> $content
	 */
	public static function flow(array $content): self {
		$item = new self();
		$item->addFlow($content);
		return $item;
	}
}
