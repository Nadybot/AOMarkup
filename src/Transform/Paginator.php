<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Export\Exporter;

/**
 * Splits a document into pages that each fit into $maxLength bytes of the target format
 *
 * Breaks between blocks, sections and table rows; honours <keep>, <pagebreak>,
 * <repeat> and repeats header rows of tables.
 */
final class Paginator {
	/** @return list<Document> */
	public function paginate(Document $document, Exporter $exporter, RenderContext $context, int $maxLength): array {
		throw NotImplementedException::for(self::class);
	}
}
