<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** One key (<dt>) with its values (<dd>); the first value is next to the key, the others below it */
final class Field implements Node {
	/** @var list<Node> */
	public readonly array $name;

	/** @var list<FieldValue> */
	public readonly array $values;

	/** @param list<Node> $name Inline nodes */
	public function __construct(array $name, FieldValue ...$values) {
		$this->name = $name;
		$this->values = array_values($values);
	}
}
