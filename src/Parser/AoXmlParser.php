<?php declare(strict_types=1);

namespace AO\Markup\Parser;

use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Import\Importer;
use AO\Markup\Widget\WidgetRegistry;
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Parser for AoXML, see docs/AoXML.md
 *
 * Never throws on bad input: it recovers and reports problems as warnings.
 * Inline <popup>s are hoisted into Document::$windows.
 */
final class AoXmlParser implements Importer {
	public function __construct(
		private WidgetRegistry $widgets=new WidgetRegistry(),
		private LoggerInterface $logger=new NullLogger(),
	) {
	}

	public function parse(string $input): Document {
		throw NotImplementedException::for('AoXML parsing');
	}

	public function import(string $input): Document {
		return $this->parse($input);
	}
}
