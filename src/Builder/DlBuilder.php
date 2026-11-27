<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\{Field, FieldList, Node};
use Stringable;

/** A field list, <dl>; takes only fields */
final class DlBuilder extends BlockBuilder {
	/** @var list<DtBuilder|Field> */
	private array $fields = [];

	/** Add fields: B::dt() */
	public function add(DtBuilder|Field ...$fields): static {
		array_push($this->fields, ...array_values($fields));
		return $this;
	}

	/**
	 * Add a field with these values
	 *
	 * A DdBuilder is used as it is, anything else is the content of a <dd>. Values that
	 * are null are left out; without values, the whole field is left out.
	 */
	public function addField(
		string|int|float|Stringable|Node|InlineBuilder $name,
		null|string|int|float|Stringable|Node|InlineBuilder|DdBuilder ...$values,
	): static {
		$dds = [];
		foreach ($values as $value) {
			if ($value !== null) {
				$dds []= $value instanceof DdBuilder ? $value : (new DdBuilder())->add($value);
			}
		}
		return count($dds) === 0 ? $this : $this->add((new DtBuilder($name))->add(...$dds));
	}

	public function node(BuildContext $context): FieldList {
		return new FieldList(...array_map(
			static fn (DtBuilder|Field $field): Field => $field instanceof DtBuilder ? $field->field($context) : $field,
			$this->fields
		));
	}

	public function build(): FieldList {
		return $this->node(new BuildContext());
	}
}
