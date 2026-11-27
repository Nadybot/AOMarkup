<?php declare(strict_types=1);

namespace AO\Markup\Tests\Context;

use AO\Markup\Context\DefaultTimeFormatter;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;

final class DefaultTimeFormatterTest extends TestCase {
	/** @return iterable<string,array{int,string}> */
	public static function provideDurations(): iterable {
		yield 'ban' => [529_200, '6 days 3 hrs'];
		yield 'zero' => [0, '0 secs'];
		yield 'one second' => [1, '1 sec'];
		yield 'seconds only below a minute' => [59, '59 secs'];
		yield 'one minute' => [60, '1 min'];
		yield 'seconds are dropped' => [61, '1 min'];
		yield 'one hour' => [3_600, '1 hr'];
		yield 'day, hour, minute' => [90_061, '1 day 1 hr 1 min'];
		yield 'plural' => [2 * 86_400 + 2 * 3_600 + 2 * 60, '2 days 2 hrs 2 mins'];
		yield 'years of 365 days' => [31_536_000 + 60, '1 year 1 min'];
		yield 'negative' => [-3_660, '-1 hr 1 min'];
	}

	#[DataProvider('provideDurations')]
	public function testDuration(int $seconds, string $expected): void {
		$this->assertSame($expected, (new DefaultTimeFormatter())->duration($seconds));
	}

	public function testDefaultsToUtc(): void {
		$formatter = new DefaultTimeFormatter();
		$value = new DateTimeImmutable('2026-11-26T18:02:00+01:00');
		$this->assertSame('2026-11-26 17:02', $formatter->dateTime($value));
		$this->assertSame('2026-11-26', $formatter->date($value));
		$this->assertSame('17:02', $formatter->time($value));
	}

	public function testTimeZoneAndFormats(): void {
		$formatter = new DefaultTimeFormatter(new DateTimeZone('Europe/Berlin'), 'd.m.Y H:i', 'd.m.Y', 'H:i:s');
		$value = new DateTimeImmutable('2026-11-26T23:30:00Z');
		$this->assertSame('27.11.2026 00:30', $formatter->dateTime($value));
		$this->assertSame('27.11.2026', $formatter->date($value));
		$this->assertSame('00:30:00', $formatter->time($value));
	}
}
