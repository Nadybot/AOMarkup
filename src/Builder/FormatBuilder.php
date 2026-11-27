<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Node\Node;
use Psr\Log\LoggerInterface;

/** B::format(): inline AoXML with {name} slots filled by arguments */
final class FormatBuilder extends InlineBuilder {
	private Template $template;

	/** @var array<string,mixed> */
	private array $args;

	/** @param array<int|string,mixed> $args */
	public function __construct(string $format, array $args, LoggerInterface $logger) {
		$this->template = Template::for($format, true, $logger);
		$this->args = $this->template->arguments($args, $logger);
	}

	/** @return list<Node> */
	public function nodes(BuildContext $context): array {
		return $this->template->fill($this->args, $context);
	}
}
