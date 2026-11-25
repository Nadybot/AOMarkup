<?php declare(strict_types=1);

namespace AO\Markup\Import;

use AO\Markup\Document;

/** Reads some markup into a document. Importers never produce widgets. */
interface Importer {
	public function import(string $input): Document;
}
