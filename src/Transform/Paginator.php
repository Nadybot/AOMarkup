<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Export\Exporter;

/**
 * Splits a document into pages that each fit into $maxLength bytes of the target format
 *
 * Pages are filled as far as possible. If a window contains PageBreaks, pages end only
 * at them (at the last one that still fits); only content between two PageBreaks that
 * does not fit on a page by itself is split further. Otherwise pages end between blocks,
 * preferably before sections, and between table rows. Honours <keep>, repeats <repeat>
 * and header rows of tables. See docs/AoXML.md §2.2.
 */
final class Paginator {
	/** @return list<Document> */
	public function paginate(Document $document, Exporter $exporter, RenderContext $context, int $maxLength): array {
		throw NotImplementedException::for(self::class);
	}
}
