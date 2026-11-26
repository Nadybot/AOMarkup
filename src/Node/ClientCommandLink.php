<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A command for the game client, e.g. "/assist Nady"; without children the command is the link text */
final class ClientCommandLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $command,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
