<?php declare(strict_types=1);

namespace AO\Markup\Tests\Builder;

use AO\Markup\Build as B;
use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\AoXmlSerializer;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;

/** B::datetime(), B::date(), B::time() and B::duration() */
final class BuildTimeTest extends TestCase {
	public function testPointsInTimeFromUnixTimes(): void {
		$this->assertSame(
			"<datetime value=\"2025-11-26T16:02:00Z\"/> <date value=\"2025-11-26T16:02:00Z\"/> <time value=\"2025-11-26T16:02:00Z\"/>\n",
			self::serialize(B::document(B::datetime(1764172920), ' ', B::date(1764172920), ' ', B::time(1764172920)))
		);
	}

	public function testDatesKeepTheirTimeZone(): void {
		$this->assertSame(
			"<datetime value=\"2026-11-20T14:02:00+01:00\"/>\n",
			self::serialize(B::document(B::datetime(new DateTimeImmutable('2026-11-20T14:02:00+01:00'))))
		);
	}

	public function testNoTimeGivesTheAlternative(): void {
		$this->assertSame('Never', B::datetime(null, 'Never'));
		$this->assertSame('Never', B::datetime(0, 'Never'));
		$this->assertNull(B::date(null));
		$this->assertSame("Ban ends: Never\n", self::serialize(B::document('Ban ends: ', B::datetime(0, 'Never'))));
	}

	public function testDurations(): void {
		$this->assertSame("<duration seconds=\"529200\"/>\n", self::serialize(B::document(B::duration(529200))));
		$this->assertSame("<duration seconds=\"0\"/>\n", self::serialize(B::document(B::duration(0))), '0 seconds is a duration');
		$this->assertSame('Never', B::duration(null, 'Never'));
	}

	public function testInAFieldList(): void {
		$this->assertSame(
			"<dl>\n\t<dt>Date</dt><dd><datetime value=\"2025-11-26T16:02:00Z\"/></dd>\n\t<dt>Ban ends</dt><dd>Never</dd>\n</dl>\n",
			self::serialize(B::document(
				B::dl()
					->addField('Date', B::datetime(1764172920))
					->addField('Ban ends', B::datetime(0, 'Never'))
					->addField('Ends in', B::duration(null))
			))
		);
	}

	private static function serialize(Document $document): string {
		return (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
	}
}
