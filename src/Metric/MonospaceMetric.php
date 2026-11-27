<?php declare(strict_types=1);

namespace AO\Markup\Metric;

/** Terminal cells; wide characters (CJK, most emojis) count as 2 */
final class MonospaceMetric implements TextMetric {
	public function canDisplay(string $char): bool {
		return true;
	}

	public function width(string $text): int {
		return mb_strwidth($text, 'UTF-8');
	}

	public function spaceWidth(): int {
		return 1;
	}

	public function spacesBetween(int $from, int $to): int {
		return max(0, $to - $from);
	}
}
