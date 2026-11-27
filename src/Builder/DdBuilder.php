<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\FieldValue;

/** A value of a field, <dd>; inline content */
final class DdBuilder implements Builder {
	use HasInline;

	public function __construct(
		public readonly ?string $unit=null,
	) {
	}

	public function build(): FieldValue {
		return $this->value(new BuildContext());
	}

	/** @internal */
	public function value(BuildContext $context): FieldValue {
		return new FieldValue($this->unit, ...$this->inline($context));
	}
}
