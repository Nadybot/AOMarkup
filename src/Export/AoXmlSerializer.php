<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Metric\{MonospaceMetric, TextMetric};

/** Canonical AoXML, with hoisted <window>s */
final class AoXmlSerializer extends BaseExporter {
	public function export(Document $document, RenderContext $context): string {
		throw NotImplementedException::for(self::class);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'aoxml';
	}
}
