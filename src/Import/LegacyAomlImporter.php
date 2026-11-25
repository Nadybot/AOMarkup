<?php declare(strict_types=1);

namespace AO\Markup\Import;

use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use Psr\Log\{LoggerInterface, NullLogger};

/** Reads Nadybot/Budabot-style AOML+ (<highlight>…<end>, <tab>, text:// popups, …) and plain AOML */
final class LegacyAomlImporter implements Importer {
	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
	) {
	}

	public function import(string $input): Document {
		throw NotImplementedException::for(self::class);
	}
}
