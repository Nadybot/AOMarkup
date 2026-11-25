<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;

/** A pure Document → Document step */
interface Transformer {
	public function transform(Document $document, RenderContext $context): Document;
}
