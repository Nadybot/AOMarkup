<?php declare(strict_types=1);

namespace AO\Markup\Node;

final class Image implements InlineNode {
	public function __construct(
		public readonly ImageSource $source,
		public readonly string $id,
	) {
	}
}
