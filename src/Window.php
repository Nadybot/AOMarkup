<?php declare(strict_types=1);

namespace AO\Markup;

use AO\Markup\Node\{Container, Node};

/** The content of a popup window */
final class Window extends Container {
	/**
	 * @param string  $id    Id referenced by PopupLink::$ref
	 * @param string  $title Window title
	 * @param ?string $name  Window name; targets that support it reuse an open window of that name
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly ?string $name=null,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
