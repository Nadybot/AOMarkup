<?php declare(strict_types=1);

namespace AO\Markup\Context;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Times in one time zone with date() formats, durations like Nadybot
 *
 * Durations follow Nadybot's Util::unixtimeToReadable($seconds, false): years
 * (365 days), days, hrs and mins, each only if not 0, separated by spaces, e.g.
 * "6 days 3 hrs" or "1 year 1 min". Seconds are only shown if the duration is
 * shorter than a minute ("59 secs", "0 secs"). Negative durations get a "-".
 */
class DefaultTimeFormatter implements TimeFormatter {
	/** Unit names and their length in seconds, largest first */
	private const UNITS = [
		'year' => 31_536_000,
		'day' => 86_400,
		'hr' => 3_600,
		'min' => 60,
	];

	public function __construct(
		public readonly DateTimeZone $timezone=new DateTimeZone('UTC'),
		public readonly string $dateTimeFormat='Y-m-d H:i',
		public readonly string $dateFormat='Y-m-d',
		public readonly string $timeFormat='H:i',
	) {
	}

	public function dateTime(DateTimeInterface $value): string {
		return $this->format($value, $this->dateTimeFormat);
	}

	public function date(DateTimeInterface $value): string {
		return $this->format($value, $this->dateFormat);
	}

	public function time(DateTimeInterface $value): string {
		return $this->format($value, $this->timeFormat);
	}

	public function duration(int $seconds): string {
		if ($seconds < 0) {
			return '-' . $this->duration(-$seconds);
		}
		$parts = [];
		foreach (self::UNITS as $unit => $length) {
			$count = intdiv($seconds, $length);
			$seconds %= $length;
			if ($count > 0) {
				$parts []= self::count($count, $unit);
			}
		}
		if (count($parts) === 0) {
			$parts []= self::count($seconds, 'sec');
		}
		return implode(' ', $parts);
	}

	private function format(DateTimeInterface $value, string $format): string {
		return DateTimeImmutable::createFromInterface($value)->setTimezone($this->timezone)->format($format);
	}

	private static function count(int $count, string $unit): string {
		return "{$count} {$unit}" . ($count === 1 ? '' : 's');
	}
}
