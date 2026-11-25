<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Metric\TextMetric;
use AO\Markup\Widget\Widget;

/** Replaces all widgets without a native renderer by their fallback */
final class WidgetExpander implements Transformer {
	/** @param list<class-string<Widget>> $native Widgets the exporter renders natively */
	public function __construct(
		private TextMetric $metric,
		private array $native=[],
	) {
	}

	public function transform(Document $document, RenderContext $context): Document {
		throw NotImplementedException::for(self::class);
	}
}
