<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\InlineContent;
use AO\Markup\Node\{Node, PopupLink, Text};
use Stringable;

/**
 * The link that opens a window, made by WindowBuilder::link()
 *
 * Without content, the window title is the link text. The window id is decided when
 * the document is built, see Build::document().
 */
final class PopupBuilder extends InlineBuilder {
	use HasInline;

	public function __construct(
		public readonly WindowBuilder $window,
		null|string|int|float|Stringable|InlineContent ...$content,
	) {
		$this->add(...$content);
	}

	/** @return list<Node> */
	public function nodes(BuildContext $context): array {
		$label = $this->inline($context);
		if (count($label) === 0) {
			$label = [new Text($this->window->title)];
		}
		return [new PopupLink($context->idOf($this->window), ...$label)];
	}
}
