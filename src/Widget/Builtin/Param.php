<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Fragment, Node, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};

/** A command parameter inside <syntax>, e.g. <param>attack time</param> */
#[AsWidget('param', void: false)]
final class Param implements Widget {
	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Fragment(new Text('<'), ...[...$children, new Text('>')]);
	}
}
