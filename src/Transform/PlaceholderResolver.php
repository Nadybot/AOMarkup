<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;

/** Replaces placeholders like <myname/> and <symbol/> with text */
final class PlaceholderResolver implements Transformer {
	public function transform(Document $document, RenderContext $context): Document {
		throw NotImplementedException::for(self::class);
	}
}
