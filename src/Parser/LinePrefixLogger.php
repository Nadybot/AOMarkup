<?php declare(strict_types=1);

namespace AO\Markup\Parser;

use Psr\Log\{AbstractLogger, LoggerInterface};
use Stringable;

/**
 * Prefixes messages of code that knows nothing about lines (e.g. widget hydration)
 * with the current AoXML line
 *
 * @internal
 */
final class LinePrefixLogger extends AbstractLogger {
	public function __construct(
		private LoggerInterface $logger,
		private int $line,
	) {
	}

	/** @param array<mixed> $context */
	public function log($level, string|Stringable $message, array $context=[]): void {
		$this->logger->log($level, "AoXML line {$this->line}: {$message}", ['line' => $this->line, ...$context]);
	}
}
