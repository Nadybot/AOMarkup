<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\Discord\Markdown;
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

	public function testMarkdownHelpers(): void {
		$this->assertSame('a_b \_c d\_ \\\\ \`', Markdown::escape('a_b _c d_ \ `'));
		$this->assertSame('`` a`b ``', Markdown::code('a`b'));
		$this->assertSame("```\n`\u{200B}``\n```", Markdown::codeBlock("```\n"));
	}

	/** @return array<mixed> */
	private static function export(string $input): array {
		$logger = TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse($input);
		$context = new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger);
		return (new DiscordExporter())->toArray($document, $context);
	}
}
