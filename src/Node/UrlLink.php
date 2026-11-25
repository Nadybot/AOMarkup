<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class UrlLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $href,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
