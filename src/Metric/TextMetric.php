<?php declare(strict_types=1);

namespace AO\Markup\Metric;

/** Measures text in the unit of a target (pixels, terminal cells, …) */
interface TextMetric {
	/** Width of the given plain text */
	public function width(string $text): int;

	/** Width of a single space, the smallest unit for padding */
	public function spaceWidth(): int;

	/** Number of spaces that pad $from to (at least) $to, rounded to the nearest space */
	public function spacesBetween(int $from, int $to): int;
}
