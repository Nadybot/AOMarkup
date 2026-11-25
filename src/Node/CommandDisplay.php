<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** What to show as link text of a command without children */
enum CommandDisplay: string {
	/** "!help syntax" */
	case Command = 'command';

	/** "/tell Botname !help syntax" */
	case Tell = 'tell';
}
