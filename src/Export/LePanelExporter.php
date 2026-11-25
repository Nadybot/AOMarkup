<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Metric\{AoFontMetric, TextMetric};

/** AOML with lepanel://v1/ windows for the Lost Eden private server (draft) */
final class LePanelExporter extends BaseExporter {
	public function export(Document $document, RenderContext $context): string {
		throw NotImplementedException::for(self::class);
	}

	public function getMetric(): TextMetric {
		return new AoFontMetric();
	}

	public function getFormat(): string {
		return 'lepanel';
	}
}
