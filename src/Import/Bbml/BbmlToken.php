<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

/**
 * One token of BBML: text, a line break or a tag
 *
 * @internal
 */
final class BbmlToken {
	public const TEXT = 'text';
	public const NEWLINE = 'newline';
	public const TAG = 'tag';

	/**
	 * @param self::TEXT|self::NEWLINE|self::TAG $type
	 * @param string                             $text       Decoded text of a text token
	 * @param string                             $name       Lower-case tag name
	 * @param array<string,string>               $attributes Raw attribute values (entities not decoded)
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $text='',
		public readonly string $name='',
		public readonly bool $closing=false,
		public readonly array $attributes=[],
	) {
	}

	public function isTag(string ...$names): bool {
		return $this->type === self::TAG && !$this->closing && in_array($this->name, $names, true);
	}

	public function isClosingTag(string ...$names): bool {
		return $this->type === self::TAG && $this->closing && in_array($this->name, $names, true);
	}
}
