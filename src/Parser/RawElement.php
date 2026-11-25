<?php declare(strict_types=1);

namespace AO\Markup\Parser;

/**
 * An element of the raw tree: tags matched up, nothing interpreted yet
 *
 * @internal
 */
final class RawElement {
	/** @var list<RawElement|string> */
	public array $children = [];

	/** @var array<string,true> Attributes that were read by the converter */
	private array $used = [];

	/** @param array<string,string|true> $attributes */
	public function __construct(
		public readonly string $name,
		public readonly array $attributes,
		public readonly int $line,
	) {
	}

	public function has(string $attribute): bool {
		return array_key_exists($attribute, $this->attributes);
	}

	/** @return null|string|true true for boolean shorthand */
	public function get(string $attribute): null|string|bool {
		$this->used[$attribute] = true;
		return $this->attributes[$attribute] ?? null;
	}

	/** @return list<string> Attributes nobody asked for */
	public function unusedAttributes(): array {
		return array_values(array_diff(array_keys($this->attributes), array_keys($this->used)));
	}

	/** All text inside this element, ignoring tags */
	public function textContent(): string {
		$text = '';
		foreach ($this->children as $child) {
			$text .= is_string($child) ? $child : $child->textContent();
		}
		return $text;
	}

	public function hasElementChildren(): bool {
		foreach ($this->children as $child) {
			if ($child instanceof self) {
				return true;
			}
		}
		return false;
	}
}
