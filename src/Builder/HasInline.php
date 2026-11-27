<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Stringable;

/** A builder whose content is inline */
trait HasInline {
	/** @var list<null|string|int|float|Stringable|Node|InlineBuilder> */
	private array $content = [];

	/** Add inline content */
	public function add(null|string|int|float|Stringable|Node|InlineBuilder ...$content): static {
		array_push($this->content, ...array_values($content));
		return $this;
	}

	/** @return list<Node> */
	private function inline(BuildContext $context): array {
		return Content::inline($this->content, $context);
	}
}
