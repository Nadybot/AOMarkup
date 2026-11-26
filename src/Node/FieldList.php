<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A list of key/value fields that are aligned together (<dl>) */
final class FieldList implements BlockNode {
	/** @var list<Field> */
	public readonly array $fields;

	/** The whole list, if this is a part of it on one page */
	private ?FieldList $whole = null;

	public function __construct(Field ...$fields) {
		$this->fields = array_values($fields);
	}

	/**
	 * A part of this list with some of its fields, e.g. for one page
	 *
	 * The part is aligned like the whole list, so the values line up on every page.
	 */
	public function part(Field ...$fields): self {
		$part = new self(...$fields);
		$part->whole = $this->layout();
		return $part;
	}

	/** The list to align the fields on: the whole list this is a part of, or itself */
	public function layout(): self {
		return $this->whole ?? $this;
	}
}
