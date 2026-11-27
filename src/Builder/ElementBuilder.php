<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Closure;
use Stringable;

/**
 * An inline element with inline content: a style, a colour, a link
 *
 * The element is made by a factory from the built content.
 */
final class ElementBuilder extends InlineBuilder {
	use HasInline;

	/** @param Closure(Node...):list<Node> $factory */
	public function __construct(
		private readonly Closure $factory,
		null|string|int|float|Stringable|Node|InlineBuilder ...$content,
	) {
		$this->add(...$content);
	}

	/** @return list<Node> */
	public function nodes(BuildContext $context): array {
		return ($this->factory)(...$this->inline($context));
	}
}
