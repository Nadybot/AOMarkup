<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;

/** Groups consecutive Fields into FieldGroups, so they are aligned together */
final class FieldGrouper implements Transformer {
	public function transform(Document $document, RenderContext $context): Document {
		throw NotImplementedException::for(self::class);
	}
}
