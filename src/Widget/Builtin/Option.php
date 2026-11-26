<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Fragment, Node};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};

/** One option of a <choice>; the children are the label */
#[AsWidget('option', void: false)]
final class Option implements Widget {
	public function __construct(
		public readonly string $value,
		public readonly bool $selected=false,
	) {
	}

	/** Outside of a <choice>, an option is just its label */
	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Fragment(...$children);
	}
}
