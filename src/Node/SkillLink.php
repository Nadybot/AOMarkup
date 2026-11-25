<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A skill */
final class SkillLink extends Container implements InlineNode {
	public function __construct(
		public readonly int $id,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
