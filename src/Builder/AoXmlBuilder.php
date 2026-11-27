<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use AO\Markup\Content;
use AO\Markup\Node\{Fragment, Node};
use Psr\Log\LoggerInterface;

/**
 * B::aoxml(): AoXML blocks with {name} slots filled by arguments
 *
 * As content of a block container, its blocks are added in place.
 */
final class AoXmlBuilder implements Builder, Content {
	private Template $template;

	/** @var array<string,mixed> */
	private array $args;

	/** @param array<int|string,mixed> $args */
	public function __construct(string $format, array $args, LoggerInterface $logger) {
		$this->template = Template::for($format, false, $logger);
		$this->args = $this->template->arguments($args, $logger);
	}

	/** The blocks, in a Fragment */
	public function build(): Fragment {
		return new Fragment(...$this->nodes(new BuildContext()));
	}

	/**
	 * The blocks
	 *
	 * @internal
	 *
	 * @return list<Node>
	 */
	public function nodes(BuildContext $context): array {
		return $this->template->fill($this->args, $context);
	}
}
