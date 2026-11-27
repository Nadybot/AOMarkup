<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\InlineContent;
use AO\Markup\Node\{Field, FieldValue};
use Stringable;

/** A field of a field list: the name (<dt>) and its values; takes only values */
final class DtBuilder implements Builder {
	/** @var list<null|string|int|float|Stringable|InlineContent> */
	private array $name;

	/** @var list<DdBuilder|FieldValue> */
	private array $values = [];

	public function __construct(null|string|int|float|Stringable|InlineContent ...$name) {
		$this->name = array_values($name);
	}

	/** Add values: B::dd() */
	public function add(DdBuilder|FieldValue ...$values): static {
		array_push($this->values, ...array_values($values));
		return $this;
	}

	public function build(): Field {
		return $this->field(new BuildContext());
	}

	/** @internal */
	public function field(BuildContext $context): Field {
		return new Field(ContentConverter::inline($this->name, $context), ...array_map(
			static fn (DdBuilder|FieldValue $value): FieldValue => $value instanceof DdBuilder ? $value->value($context) : $value,
			$this->values
		));
	}
}
