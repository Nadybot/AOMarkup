<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class ListItem extends Container {
	public function __construct(
		public readonly ?Image $icon=null,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
