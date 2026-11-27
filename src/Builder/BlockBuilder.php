<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;

/** A builder of one block: a section, a table, a list, … */
abstract class BlockBuilder implements Builder {
	public function build(): Node {
		return $this->node(new BuildContext());
	}

	/** @internal */
	abstract public function node(BuildContext $context): Node;
}
