<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\{Build, Document, InlineContent, Window};
use Stringable;

/**
 * A window, opened by the links made with link()
 *
 * Without an id, the document gives it one (window1, window2, …), see Build::document().
 */
final class WindowBuilder implements Builder {
	use HasBlocks;

	public function __construct(
		public readonly string $title,
		public readonly ?string $id=null,
	) {
	}

	/** The popup link that opens this window; without content, the title is the link text */
	public function link(null|string|int|float|Stringable|InlineContent ...$label): PopupBuilder {
		return new PopupBuilder($this, ...$label);
	}

	/** A message that is only the link to this window, with the window */
	public function asDocument(null|string|int|float|Stringable|InlineContent $link=null): Document {
		return Build::document($this->link($link));
	}

	/** The window on its own; without an id, it is "window1" */
	public function build(): Window {
		return $this->window($this->id ?? 'window1', new BuildContext());
	}

	/** @internal */
	public function window(string $id, BuildContext $context): Window {
		return new Window($id, $this->title, ...$this->blocks($context));
	}
}
