<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Metric\TextMetric;

interface Exporter {
	/** Render the document into the target format */
	public function export(Document $document, RenderContext $context): string;

	/** The metric used to align text in this target */
	public function getMetric(): TextMetric;

	/** Short name of the format, used for file names of the samples */
	public function getFormat(): string;
}
