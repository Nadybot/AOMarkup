<?php declare(strict_types=1);

namespace AO\Markup\Node;

/** How a command reaches the bot */
enum CommandChannel: string {
	/** "/tell Botname !help" */
	case Tell = 'tell';

	/** The bot's private channel: "/g Botname !help" */
	case Priv = 'priv';

	/** The org channel: "/o !help" */
	case Org = 'org';
}
