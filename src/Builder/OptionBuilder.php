<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Widget\Builtin\Option;
use AO\Markup\Widget\WidgetNode;

/** An option of a choice, <option>; the content is the label */
final class OptionBuilder implements Builder {
	use HasInline;

	public function __construct(
		public readonly string $value,
		public readonly bool $selected=false,
	) {
	}

	public function build(): WidgetNode {
		return $this->option(new BuildContext());
	}

	/** @internal */
	public function option(BuildContext $context): WidgetNode {
		return new WidgetNode(new Option($this->value, $this->selected), ...$this->inline($context));
	}
}
