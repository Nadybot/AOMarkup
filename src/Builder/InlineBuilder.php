<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\InlineContent;

use AO\Markup\Node\{Fragment, Node};

/** A builder of inline content: styles, links, text */
abstract class InlineBuilder implements Builder, InlineContent {
	/** A single node, or a Fragment if the content is not exactly one node */
	public function build(): Node {
		$nodes = $this->nodes(new BuildContext());
		return count($nodes) === 1 ? $nodes[0] : new Fragment(...$nodes);
	}

	/**
	 * The inline nodes
	 *
	 * @internal
	 *
	 * @return list<Node>
	 */
	abstract public function nodes(BuildContext $context): array;
}
