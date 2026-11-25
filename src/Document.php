<?php declare(strict_types=1);

namespace AO\Markup;

use AO\Markup\Node\Fragment;

/** A message: its body plus all windows referenced from it */
final class Document {
	/** @param array<string,Window> $windows Windows by id */
	public function __construct(
		public readonly Fragment $body=new Fragment(),
		public readonly array $windows=[],
	) {
	}

	public function getWindow(string $id): ?Window {
		return $this->windows[$id] ?? null;
	}

	public function withBody(Fragment $body): self {
		return new self($body, $this->windows);
	}

	public function withWindow(Window $window): self {
		$windows = $this->windows;
		$windows[$window->id] = $window;
		return new self($this->body, $windows);
	}
}
