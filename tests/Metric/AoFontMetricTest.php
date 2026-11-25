<?php declare(strict_types=1);

namespace AO\Markup\Tests\Metric;

use AO\Markup\Metric\{AoFontMetric, MonospaceMetric};
use PHPUnit\Framework\TestCase;

final class AoFontMetricTest extends TestCase {
	public function testWidth(): void {
		$metric = new AoFontMetric();
		$this->assertSame(37, $metric->width('Date:'));
		$this->assertSame(0, $metric->width(''));
		$this->assertSame(AoFontMetric::DEFAULT_WIDTH, $metric->width('♥'));
	}

	public function testSpacesBetweenRoundsToNearestSpace(): void {
		$metric = new AoFontMetric();
		$this->assertSame(0, $metric->spacesBetween(10, 5));
		$this->assertSame(2, $metric->spacesBetween(0, 12));
		$this->assertSame(3, $metric->spacesBetween(0, 13));
	}

	public function testMonospace(): void {
		$metric = new MonospaceMetric();
		$this->assertSame(5, $metric->width('Date:'));
		$this->assertSame(1, $metric->width('│'));
		$this->assertSame(3, $metric->spacesBetween(2, 5));
	}
}
