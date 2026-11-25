<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use AO\Markup\Node\{Container, Node};

/** A widget instance in the document tree */
final class WidgetNode extends Container {
	public function __construct(
		public readonly Widget $widget,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
