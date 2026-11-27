<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Closure;

/** A block container: section, indent, center, keep, repeat */
final class ContainerBuilder extends BlockBuilder {
	use HasBlocks;

	/** @param Closure(Node...):Node $factory Makes the element from its built blocks */
	public function __construct(
		private readonly Closure $factory,
	) {
	}

	public function node(BuildContext $context): Node {
		return ($this->factory)(...$this->blocks($context));
	}
}
