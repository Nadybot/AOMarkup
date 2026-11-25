<?php declare(strict_types=1);

namespace AO\Markup\Import;

use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Reads BBML, the Budabot markup language (<highlight>…<end>, <tab>, text:// popups, …)
 *
 * Funcom AOML is a subset of BBML, so plain AOML is read as well: <font color> becomes a
 * Color node, colours are never guessed back into semantic styles.
 */
final class BbmlImporter implements Importer {
	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
	) {
	}

	public function import(string $input): Document {
		throw NotImplementedException::for(self::class);
	}
}
