<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** Base class for every node that has child nodes */
abstract class Container implements Node {
	/** @var list<Node> */
	public readonly array $children;

	public function __construct(Node ...$children) {
		$this->children = array_values($children);
	}
}
