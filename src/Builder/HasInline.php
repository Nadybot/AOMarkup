<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\InlineContent;
use AO\Markup\Node\Node;
use Stringable;

/** A builder whose content is inline */
trait HasInline {
	/** @var list<null|string|int|float|Stringable|InlineContent> */
	private array $content = [];

	/** Add inline content */
	public function add(null|string|int|float|Stringable|InlineContent ...$content): static {
		array_push($this->content, ...array_values($content));
		return $this;
	}

	/** @return list<Node> */
	private function inline(BuildContext $context): array {
		return ContentConverter::inline($this->content, $context);
	}
}
