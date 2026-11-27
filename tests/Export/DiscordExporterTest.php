<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\Discord\{Limits, Markdown};
use AO\Markup\Export\DiscordExporter;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\TestCase;

/** Discord cases the samples don't cover; the samples are in SamplesTest */
final class DiscordExporterTest extends TestCase {
	public function testMessageWithAFieldEmbed(): void {
		$this->assertSame(
			[
				'content' => 'See this.',
				'embeds' => [[
					'title' => 'T',
					'description' => 'Intro',
					'fields' => [
						['name' => 'A', 'value' => 'a'],
						['name' => 'B', 'value' => "K: **1**\n**2**"],
					],
					'footer' => ['text' => 'F x'],
				]],
			],
			self::export(
				'See <popup window="w">this</popup>.<window id="w" title="T">Intro<section title="A">a</section>'
				. '<section title="B"><dl><dt>K</dt><dd>1</dd><dd>2</dd></dl></section><footer>F <strong>x</strong></footer></window>'
			)
		);
	}

	public function testBlocksAndLinks(): void {
		$this->assertSame(
			['content' => "3. a\n   b\n\n" . str_repeat('─', 20) . "\n\n```\na  b\n```\nafter [X](https://x.org) https://y.org"
				. ' `/assist X` [N](https://auno.org/ao/db.php?id=5) __u__ \*\_x\_\* a_b'],
			self::export(
				'<ol start="3"><li>a<br>b</li></ol><hr/><table><tr><td>a</td><td>b</td></tr></table>'
				. 'after <url href="https://x.org">X</url> <url href="https://y.org"/> <client>/assist X</client>'
				. ' <nano id="5">N</nano> <u>u</u> *_x_* a_b'
			)
		);
	}

	public function testLongFieldsContinue(): void {
		$lines = implode('<br>', array_map(static fn (int $i): string => sprintf('line %03d %s', $i, str_repeat('x', 40)), range(1, 30)));
		$message = self::export('<popup window="w">W</popup><window id="w" title="T"><section title="S">' . $lines . '</section></window>');
		$fields = $message['embeds'][0]['fields'] ?? [];
		$this->assertSame(['S', 'S (continued)'], array_column($fields, 'name'));
		$this->assertStringStartsWith('line 001 ', $fields[0]['value']);
		$this->assertStringStartsWith('line 021 ', $fields[1]['value']);
	}

	public function testMessagesBeyondTheLimitsAreSplit(): void {
		$rows = implode('', array_map(static fn (int $i): string => "<tr><td>row {$i}</td><td>" . str_repeat('y', 60) . '</td></tr>', range(1, 120)));
		$logger = TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse(
			'<popup window="w">W</popup><window id="w" title="T"><table border><tr><th>A</th><th>B</th></tr>' . $rows . '</table></window>'
		);
		$messages = (new DiscordExporter())->toMessages($document, new RenderContext(new ArrayPlaceholderProvider([]), logger: $logger));
		$this->assertGreaterThan(1, count($messages));
		foreach ($messages as $i => $message) {
			$this->assertTrue(Limits::fits($message));
			$this->assertSame('T (Page ' . ($i + 1) . ' / ' . count($messages) . ')', $message['embeds'][0]['title'] ?? null);
		}
		$this->assertSame([], $logger->records->all());
	}

	public function testLimits(): void {
		$this->assertTrue(Limits::fits(['content' => str_repeat('a', 2_000)]));
		$this->assertFalse(Limits::fits(['content' => str_repeat('a', 2_001)]));
		$this->assertFalse(Limits::fits(['embeds' => [['description' => str_repeat('ä', 4_097)]]]));
		$this->assertFalse(Limits::fits(['embeds' => [['fields' => array_fill(0, 26, ['name' => 'n', 'value' => 'v'])]]]));
		$this->assertFalse(Limits::fits(['embeds' => [['description' => str_repeat('a', 4_000)], ['description' => str_repeat('a', 2_001)]]]));
	}

	public function testMarkdownHelpers(): void {
		$this->assertSame('a_b \_c d\_ \\\\ \`', Markdown::escape('a_b _c d_ \ `'));
		$this->assertSame('`` a`b ``', Markdown::code('a`b'));
		$this->assertSame("```\n`\u{200B}``\n```", Markdown::codeBlock("```\n"));
		$this->assertSame(['ab', 'cd', 'e', 'fgh'], Markdown::chunks("ab\ncd\ne\nfgh", 3));
		$this->assertSame(['abc', 'de'], Markdown::chunks('abcde', 3));
	}

	public function testTimeWidgets(): void {
		$this->assertSame(
			[
				'content' => 'Until <t:1795712520:f> (<t:1795712520:d>, <t:1795712520:t>), in **6 days 3 hrs**.',
			],
			self::export(
				'Until <datetime value="2026-11-26T18:02:00+01:00"/> (<date value="2026-11-26T17:02:00Z"/>,'
				. ' <time value="2026-11-26T17:02:00Z"/>), in <strong><duration seconds="529200"/></strong>.'
			)
		);
	}

	public function testTimesWhereDiscordCannotShowTimestamps(): void {
		$this->assertSame(
			[
				'content' => '',
				'embeds' => [[
					'title' => 'T',
					'description' => "```\nA                │ B\n─────────────────┼─────\n2026-11-26 17:02 │ 1 hr\n```",
					'footer' => ['text' => 'As of 2026-11-26 17:02'],
				]],
			],
			self::export(
				'<popup window="w">T</popup><window id="w" title="T">'
				. '<table border><tr><th>A</th><th>B</th></tr><tr><td><datetime value="2026-11-26T17:02:00Z"/></td><td><duration seconds="3600"/></td></tr></table>'
				. '<footer>As of <datetime value="2026-11-26T17:02:00Z"/></footer></window>'
			)
		);
	}

	/** @return array<mixed> */
	private static function export(string $input): array {
		$logger = TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse($input);
		$context = new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger);
		return (new DiscordExporter())->toMessages($document, $context)[0];
	}
}
