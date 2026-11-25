<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{Node, Style, StyleName, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};

/** Marks something as not available in game */
#[AsWidget('unavailable')]
final class Unavailable implements Widget {
	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		return new Style(StyleName::Red, new Text('(!)'));
	}
}
