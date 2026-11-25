<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Metric\{MonospaceMetric, TextMetric};

/** Terminal output with 24-bit colours, OSC 8 hyperlinks and monospace tables */
final class AnsiExporter extends BaseExporter {
	/** @param string $bullet Prefix of unordered list items, followed by a space */
	public function __construct(
		public readonly string $bullet='•',
	) {
	}

	public function export(Document $document, RenderContext $context): string {
		throw NotImplementedException::for(self::class);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'ansi';
	}
}
