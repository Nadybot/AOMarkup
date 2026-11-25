<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Metric\{AoFontMetric, TextMetric};

/**
 * BBML, the Budabot markup language: AOML that keeps the named styles
 *
 * Like AomlExporter, but <highlight>, <header2>, <green>, … stay named and are closed
 * with <end>, so the receiving bot applies its own theme. Placeholders are resolved,
 * since the receiver would fill in its own name. Meant for relays to bots that do
 * not understand AoXML.
 */
final class BbmlExporter extends BaseExporter {
	/** @param string $bullet Prefix of unordered list items, followed by a space */
	public function __construct(
		public readonly string $bullet='-',
	) {
	}

	public function export(Document $document, RenderContext $context): string {
		throw NotImplementedException::for(self::class);
	}

	public function getMetric(): TextMetric {
		return new AoFontMetric();
	}

	public function getFormat(): string {
		return 'bbml';
	}
}
