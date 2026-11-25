<?php declare(strict_types=1);

namespace AO\Markup\Context;

final class ArrayPlaceholderProvider implements PlaceholderProvider {
	/** @param array<string,string> $values */
	public function __construct(
		private array $values,
	) {
	}

	public function get(string $name): ?string {
		return $this->values[$name] ?? null;
	}
}
