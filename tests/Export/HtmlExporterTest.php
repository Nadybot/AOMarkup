<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\HtmlExporter;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** HTML cases the samples don't cover; the samples are in SamplesTest */
final class HtmlExporterTest extends TestCase {
	/** @return iterable<string,array{string,string}> */
	public static function provideCases(): iterable {
		yield 'several blocks in a container keep their paragraphs' => [
			"<indent>a\n\nb</indent>",
			"<div class=\"aom-message\">\n<div class=\"aom-indent\">\n<p>a</p>\n<p>b</p>\n</div>\n</div>",
		];
		yield 'table without header, centred colspan' => [
			'<table><tr><td>a</td><td align="center" colspan="2">b</td></tr></table>',
			"<div class=\"aom-message\">\n<table class=\"aom-table\">\n<tbody>\n"
			. "<tr><td>a</td><td class=\"aom-center\" colspan=\"2\">b</td></tr>\n</tbody>\n</table>\n</div>",
		];
		yield 'blocks' => [
			'<ol start="3"><li>x</li></ol><hr/><h1>H</h1><center>c</center>',
			"<div class=\"aom-message\">\n<ol start=\"3\">\n<li>x</li>\n</ol>\n<hr>\n<h3>H</h3>\n<div class=\"aom-center\">c</div>\n</div>",
		];
		yield 'links' => [
			'<cmd via="priv" show="tell">a</cmd> <url href="https://x.org/?a=1&amp;b=2">X</url> <char>Nady</char>'
			. ' <nano id="5">N</nano> <img src="rdb:12"/> <waypoint x="1" y="2" pf="3">W</waypoint> <client>/assist X</client>',
			'<div class="aom-message"><a class="aom-cmd" data-cmd="a" data-via="priv">/g Bot !a</a>'
			. ' <a class="aom-url" href="https://x.org/?a=1&amp;b=2">X</a> <span class="aom-char" data-char="Nady">Nady</span>'
			. ' <a class="aom-nano" href="https://auno.org/ao/db.php?id=5">N</a> <span class="aom-image" data-src="rdb:12"></span>'
			. ' <span class="aom-waypoint" data-x="1" data-y="2" data-pf="3">W</span>'
			. ' <span class="aom-client" data-cmd="/assist X">/assist X</span></div>',
		];
		yield 'styles and colours' => [
			'<on>on</on> <color value="#123456">c</color> <u>u</u> "q" \'s\'',
			'<div class="aom-message"><span class="aom-on">on</span> <span class="aom-color" style="color:#123456">c</span>'
			. ' <u>u</u> &quot;q&quot; &#39;s&#39;</div>',
		];
		yield 'native widgets' => [
			'<choice command="x" via="org" inline><option value="1">A &amp; <strong>B</strong></option></choice><slider pos="5" min="-10" max="10.5"/>',
			"<div class=\"aom-message\">\n<select class=\"aom-choice\" data-cmd=\"x\" data-via=\"org\">\n"
			. "<option value=\"1\">A &amp; B</option>\n</select>\n"
			. "<div class=\"aom-slider\"><meter min=\"-10\" max=\"10.5\" value=\"5\"></meter></div>\n</div>",
		];
		yield 'time widgets' => [
			'Until <datetime value="2026-11-26T18:02:00+01:00"/> (<date value="2026-11-26T17:02:00Z"/>,'
			. ' <time value="2026-11-26T17:02:00Z"/>), in <duration seconds="529200"/>.',
			'<div class="aom-message">Until <time class="aom-datetime" datetime="2026-11-26T17:02:00Z">2026-11-26 17:02</time>'
			. ' (<time class="aom-date" datetime="2026-11-26T17:02:00Z">2026-11-26</time>,'
			. ' <time class="aom-time" datetime="2026-11-26T17:02:00Z">17:02</time>),'
			. ' in <span class="aom-duration" data-seconds="529200">6 days 3 hrs</span>.</div>',
		];
	}

	#[DataProvider('provideCases')]
	public function testHtml(string $input, string $expected): void {
		$logger = TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse($input);
		$context = new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger);
		$this->assertSame($expected, (new HtmlExporter())->export($document, $context));
		$this->assertSame([], $logger->records->all());
	}
}
