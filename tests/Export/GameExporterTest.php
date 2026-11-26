<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\{AomlExporter, BbmlExporter, Exporter};
use AO\Markup\Metric\MonospaceMetric;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AOML and BBML cases the samples don't cover; the samples are in SamplesTest */
final class GameExporterTest extends TestCase {
	/** @return iterable<string,array{string,string}> */
	public static function provideAoml(): iterable {
		yield 'quotes only escaped inside popups' => [
			'A "q" &amp; &lt;x&gt; <popup window="w">open "it"</popup>.<window id="w" title="T &quot;1&quot;">say "hi"</window>',
			"A \"q\" &amp; &lt;x&gt; <a href=\"text://<font color=#89D2E8><font color=#FFFF00>T &quot;1&quot;</font>\n\n"
			. "say &quot;hi&quot;\">open \"it\"</a>.",
		];
		yield 'links' => [
			'<nano id="5">N</nano> <char>Nady</char> <waypoint x="1" y="2" pf="585">W</waypoint> <client>/assist Nady</client>',
			"<a href='itemid://53019/5'>N</a> <a href='user://Nady'>Nady</a> <a href='chatcmd:///waypoint 1 2 585'>W</a>"
			. " <a href='chatcmd:///assist Nady'>/assist Nady</a>",
		];
		yield 'commands' => [
			'<cmd command="o&apos;neil">x</cmd> <cmd via="priv">a</cmd> <cmd via="org" show="tell">b</cmd>',
			"<a href='chatcmd:///tell Bot !o&#39;neil'>x</a> <a href='chatcmd:///g Bot !a'>!a</a>"
			. " <a href='chatcmd:///o !b'>/o !b</a>",
		];
		yield 'urls keep their query' => [
			'<url href="https://x.org/?a=1&amp;b=2">X</url>',
			"<a href='chatcmd:///start https://x.org/?a=1&b=2'>X</a>",
		];
		yield 'images' => [
			'<img src="rdb:12"/> <img src="tdb:GFX_X"/>',
			'<img src=rdb://12> <img src=tdb://id:GFX_X>',
		];
		yield 'left-aligned numbers get the zeros behind' => [
			'<table><tr><td>1</td><td>abc</td></tr><tr><td>100</td><td>d</td></tr></table>',
			"1<font color=#000000>00</font>  abc\n100  d",
		];
		yield 'lists, rule and centre' => [
			'<ol><li>a</li><li>b<br>c</li></ol><hr/><center>x</center>',
			"1. a\n2. b\n    c\n\n<img src=tdb://id:GFX_GUI_FRIENDLIST_SPLITTER>\n\n<center>x</center>",
		];
		yield 'a popup link inside a window is just text' => [
			'<popup window="a">A</popup><window id="a" title="A">x</window><window id="b" title="B"><popup window="a">A</popup></window>',
			"<a href=\"text://<font color=#89D2E8><font color=#FFFF00>A</font>\n\nx\">A</a>",
		];
	}

	#[DataProvider('provideAoml')]
	public function testAoml(string $input, string $expected): void {
		$this->assertSame($expected, self::export(new AomlExporter(), $input));
	}

	public function testBbmlIndentsWithTabs(): void {
		$this->assertSame(
			"<header2>A<end>\n<tab><header2>B<end>\n<tab><tab>x\n<tab><tab>y",
			self::export(new BbmlExporter(), '<section title="A"><section title="B">x<br>y</section></section>')
		);
	}

	public function testBbmlKeepsStyleNames(): void {
		$this->assertSame(
			'<highlight>a<end> <on>b<end> <red>[<end>c<red>]<end> <font color=#123456>d</font> <u>e</u>',
			self::export(new BbmlExporter(), '<strong>a</strong> <on>b</on> <match>c</match> <color value="#123456">d</color> <u>e</u>')
		);
	}

	public function testTheMetricCanBeReplaced(): void {
		$this->assertSame(
			"A:   <font color=#FFFFFF>1</font>\nBcd: <font color=#FFFFFF>2</font>",
			self::export(new AomlExporter(metric: new MonospaceMetric()), '<dl><dt>A</dt><dd>1</dd><dt>Bcd</dt><dd>2</dd></dl>')
		);
	}

	private static function export(Exporter $exporter, string $input): string {
		$logger = TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse($input);
		$context = new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger);
		return $exporter->export($document, $context);
	}
}
