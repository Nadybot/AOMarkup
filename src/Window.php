<?php declare(strict_types=1);

namespace AO\Markup;

use AO\Markup\Node\{Container, Node};

/** The content of a popup window */
final class Window extends Container {
	/**
	 * @param string $id    Id referenced by PopupLink::$window
	 * @param string $title Window title
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
