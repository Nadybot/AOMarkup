<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Metric\TextMetric;
use AO\Markup\Node\Node;
use AO\Markup\Widget\{ExpansionContext, Widget, WidgetNode};

/**
 * Replaces all widgets without a native renderer by their fallback
 *
 * A widget gets its children unexpanded (a <choice> needs its <option>s), and its
 * fallback is expanded again afterwards. Natively rendered widgets are kept as
 * they are, children included; their renderer is responsible for them.
 */
final class WidgetExpander implements Transformer {
	/** @param list<class-string<Widget>> $native Widgets the exporter renders natively */
	public function __construct(
		private TextMetric $metric,
		private array $native=[],
	) {
	}

	public function transform(Document $document, RenderContext $context): Document {
		$ctx = new ExpansionContext($context, $this->metric);
		$expand = function (Node $node) use (&$expand, $ctx): Node {
			if (!$node instanceof WidgetNode) {
				return Tree::mapChildren($node, $expand);
			}
			if (in_array($node->widget::class, $this->native, true)) {
				return $node;
			}
			return $expand($node->widget->fallback($ctx, ...$node->children));
		};
		return Tree::mapDocument($document, $expand);
	}
}
