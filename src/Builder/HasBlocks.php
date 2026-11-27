<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Stringable;

/** A block container: any blocks, every piece of inline content becomes a paragraph */
trait HasBlocks {
	/** @var list<list<null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder>> Flow content, see Content::flow() */
	private array $content = [];

	/**
	 * Add blocks
	 *
	 * Each argument is one block: a string, number, inline node or inline builder
	 * becomes a paragraph of its own. Use B::p() for a paragraph of several pieces.
	 */
	public function add(null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder ...$content): static {
		foreach ($content as $piece) {
			$this->content []= [$piece];
		}
		return $this;
	}

	/**
	 * Add flow content: blocks stay blocks, each run of inline pieces is one paragraph
	 *
	 * @param list<null|string|int|float|Stringable|Node|InlineBuilder|BlockBuilder> $content
	 */
	private function addFlow(array $content): void {
		$this->content []= $content;
	}

	/** @return list<Node> */
	private function blocks(BuildContext $context): array {
		$blocks = [];
		foreach ($this->content as $flow) {
			array_push($blocks, ...Content::flow($flow, $context));
		}
		return $blocks;
	}
}
