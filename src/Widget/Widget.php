<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use AO\Markup\Node\Node;

/**
 * A registered element with a mandatory fallback to core nodes
 *
 * Attributes are the constructor parameters. Implementations must be
 * annotated with #[AsWidget('name')].
 */
interface Widget {
	/**
	 * Express this widget with core nodes only, so it works in every exporter
	 *
	 * @param Node ...$children The widget's children (for non-void widgets)
	 */
	public function fallback(ExpansionContext $ctx, Node ...$children): Node;
}
