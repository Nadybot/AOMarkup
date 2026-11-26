<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Text\{MonospaceRenderer, PlainStyler};
use AO\Markup\Metric\{MonospaceMetric, TextMetric};
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};

/** Plain UTF-8 text with monospace tables */
final class PlainExporter extends BaseExporter {
	/** @param string $bullet Prefix of unordered list items, followed by a space */
	public function __construct(
		public readonly string $bullet='•',
	) {
	}

	public function export(Document $document, RenderContext $context): string {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->getMetric()))->transform($document, $context);
		return (new MonospaceRenderer($context, $this->getMetric(), new PlainStyler(), $this->bullet))->render($document);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'plain';
	}
}
