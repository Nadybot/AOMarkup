<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** A command for this bot, without command prefix */
final class CommandLink extends Container implements InlineNode {
	public function __construct(
		public readonly string $command,
		public readonly CommandDisplay $show=CommandDisplay::Command,
		public readonly CommandChannel $via=CommandChannel::Tell,
		Node ...$children,
	) {
		parent::__construct(...$children);
	}
}
