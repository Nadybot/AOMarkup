<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use Attribute;

/**
 * Declares the AoXML element name of a widget
 *
 * $void:  the element has no content
 * $block: the widget stands on its own like a paragraph, instead of flowing in a line
 * $only:  the element contains only these child elements (e.g. ['option']); the
 *         whitespace between them is ignored, anything else is dropped with a warning
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsWidget {
	/** @param ?list<string> $only */
	public function __construct(
		public readonly string $name,
		public readonly bool $void=true,
		public readonly bool $block=false,
		public readonly ?array $only=null,
	) {
	}
}
