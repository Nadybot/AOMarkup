<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use Attribute;

/** Declares the AoXML element name of a widget */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsWidget {
	public function __construct(
		public readonly string $name,
		public readonly bool $void=true,
	) {
	}
}
