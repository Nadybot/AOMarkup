<?php declare(strict_types=1);

namespace AO\Markup\Transform;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Node\{Node, Placeholder, Text};

/**
 * Replaces placeholders like <myname/> and <symbol/> with text
 *
 * Unknown placeholders are logged and replaced by nothing.
 */
final class PlaceholderResolver implements Transformer {
	public function transform(Document $document, RenderContext $context): Document {
		$resolve = static function (Node $node) use (&$resolve, $context): Node {
			if (!$node instanceof Placeholder) {
				return Tree::mapChildren($node, $resolve);
			}
			$value = $context->placeholders->get($node->name);
			if ($value === null) {
				$context->logger->warning('No value for the placeholder "{name}"', ['name' => $node->name]);
			}
			return new Text($value ?? '');
		};
		return Tree::mapDocument($document, $resolve);
	}
}
