<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** Consecutive fields that are aligned together, created by the FieldGrouper */
final class FieldGroup implements BlockNode {
	/** @var list<Field> */
	public readonly array $fields;

	public function __construct(Field ...$fields) {
		$this->fields = array_values($fields);
	}
}
