<?php declare(strict_types=1);

namespace AO\Markup\Context;

use DateTimeInterface;

/**
 * Formats points in time and durations the way the bot shows them
 *
 * Used by the fallbacks of <datetime>, <date>, <time> and <duration>, and for the
 * visible text of targets that render them natively (HTML).
 */
interface TimeFormatter {
	/** A date with time, e.g. "2026-11-20 14:02" */
	public function dateTime(DateTimeInterface $value): string;

	/** A date only, e.g. "2026-11-20" */
	public function date(DateTimeInterface $value): string;

	/** A time of day only, e.g. "14:02" */
	public function time(DateTimeInterface $value): string;

	/** A length of time, e.g. "6 days 3 hrs" */
	public function duration(int $seconds): string;
}
