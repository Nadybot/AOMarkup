<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Closure;

/** A block with inline content: paragraph, heading, footer */
final class InlineBlockBuilder extends BlockBuilder {
	use HasInline;

	/** @param Closure(Node...):Node $factory Makes the block from its built content */
	public function __construct(
		private readonly Closure $factory,
	) {
	}

	public function node(BuildContext $context): Node {
		return ($this->factory)(...$this->inline($context));
	}
}
