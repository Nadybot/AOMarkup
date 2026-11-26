<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A list of key/value fields that are aligned together (<dl>) */
final class FieldList implements BlockNode {
	/** @var list<Field> */
	public readonly array $fields;

	public function __construct(Field ...$fields) {
		$this->fields = array_values($fields);
	}
}
