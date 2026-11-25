<?php declare(strict_types=1);

namespace AO\Markup\Parser;

/**
 * One lexical token of AoXML
 *
 * @internal
 */
final class Token {
	/** @param array<string,string|true> $attributes Attribute values; true for boolean shorthand */
	public function __construct(
		public readonly TokenType $type,
		public readonly int $line,
		public readonly string $text='',
		public readonly string $name='',
		public readonly array $attributes=[],
		public readonly bool $selfClosing=false,
	) {
	}
}
