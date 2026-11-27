<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Stringable;

/** A sequence of inline content, without an element around it */
final class TextBuilder extends InlineBuilder {
	use HasInline;

	public function __construct(null|string|int|float|Stringable|Node|InlineBuilder ...$content) {
		$this->add(...$content);
	}

	/** @return list<Node> */
	public function nodes(BuildContext $context): array {
		return $this->inline($context);
	}
}
