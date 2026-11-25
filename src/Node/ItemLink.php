<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class ItemLink extends Container implements InlineNode {
	public function __construct(
		public readonly int $lowId,
		public readonly int $highId,
		public readonly int $ql,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
