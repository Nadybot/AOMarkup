<?php declare(strict_types=1);

namespace AO\Markup\Tests\Import;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\AoXmlSerializer;
use AO\Markup\Import\BbmlImporter;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Import cases the samples don't cover; the samples are in SamplesTest::testImport() */
final class BbmlImporterTest extends TestCase {
	/** @return iterable<string,array{string,string,list<string>}> */
	public static function provideCases(): iterable {
		yield 'colours stay colours, black padding is dropped' => [
			'a <font color=#123456>b</font> <font color=#000000>00</font>1 <black>0</black>',
			"a <color value=\"#123456\">b</color> 1\n",
			[],
		];
		yield 'styles spanning lines' => [
			"<highlight>a\nb<end> c",
			"<strong>a</strong>\n<strong>b</strong> c\n",
			[],
		];
		yield 'leading tabs indent, nested' => [
			"x\n<tab><tab>deep\n<tab>flat\n\n<tab>again",
			"x\n\n<indent>\n\t<indent>\n\t\tdeep\n\t</indent>\n\n\tflat\n\n\tagain\n</indent>\n",
			[],
		];
		yield 'four leading spaces are a tab' => [
			"x\n    y\n  z",
			"x\n\n<indent>\n\ty\n</indent>\n\nz\n",
			[],
		];
		yield 'links' => [
			"<a href='user://Nady'>Nady</a> <a href='user://Nady'>she</a> <a href='itemid://53019/123'>N</a>"
			. " <a href='chatcmd:///waypoint 10 20 585'>W</a> <a href='chatcmd:///start https://x.org'>X</a>"
			. " <a href='chatcmd:///g <myname> <symbol>join'>join</a> <a href='chatcmd:///o <symbol>online'><symbol>online</a>"
			. " <a href='chatcmd:///assist Nady'>/assist Nady</a> <a href='foo://bar'>F</a>",
			'<char>Nady</char> <char name="Nady">she</char> <nano id="123">N</nano> <waypoint x="10" y="20" pf="585">W</waypoint>'
			. ' <url href="https://x.org">X</url> <cmd command="join" via="priv">join</cmd> <cmd via="org">online</cmd>'
			. " <client>/assist Nady</client> F\n",
			['BBML: unknown link target "foo://bar", only the link text is kept'],
		];
		yield 'images' => [
			'<img src=rdb://123> <img src=tdb://id:GFX_GUI_X> <img src=nope>',
			"<img src=\"rdb:123\"/> <img src=\"tdb:GFX_GUI_X\"/>\n",
			['BBML: unknown image source "nope"'],
		];
		yield 'several popups, no nesting' => [
			"<a href=\"text://<header>A<end>\n\nx <a href=&quot;text://y&quot;>nested</a>\">A</a> and <a href=\"text://B content\">B</a>",
			"<popup window=\"window1\">A</popup> and <popup window=\"window2\">B</popup>\n\n"
			. "<window id=\"window1\" title=\"A\">\n\tx nested\n</window>\n\n<window id=\"window2\" title=\"B\">\n\tB content\n</window>\n",
			['BBML: popups inside popups are not supported, only the link text is kept'],
		];
		yield 'center and broken markup' => [
			'<center>c1<br>c2</center>rest <end> </font> <blink>x</blink>',
			"<center>\n\tc1\n\tc2\n</center>\n\nrest x\n",
			['BBML: <end> without an opening tag', 'BBML: </font> without an opening tag', 'BBML: unknown tag <blink>'],
		];
	}

	/** @param list<string> $warnings */
	#[DataProvider('provideCases')]
	public function testImport(string $bbml, string $aoxml, array $warnings): void {
		$logger = TestLogger::create();
		$this->assertSame($aoxml, self::serialize((new BbmlImporter($logger))->import($bbml)));
		$this->assertSame($warnings, array_map(static fn ($r): string => (string)$r->message, $logger->records->all()));
	}

	/** @return iterable<string,array{string,string}> */
	public static function provideTables(): iterable {
		yield 'two rows' => ["a | b\nc | d", "<table border=\"true\">\n\t<tr><td>a</td><td>b</td></tr>\n\t<tr><td>c</td><td>d</td></tr>\n</table>\n"];
		yield 'header line and underline, numbers right-aligned' => [
			"Name     Value\n-----\nx | 1\ny | 22",
			"<table border=\"true\">\n\t<tr><th>Name</th><th align=\"right\">Value</th></tr>\n\t<tr><td>x</td><td>1</td></tr>\n\t<tr><td>y</td><td>22</td></tr>\n</table>\n",
		];
		yield 'underlined first row' => [
			"<u>A | B</u>\nx | y",
			"<table border=\"true\">\n\t<tr><th>A</th><th>B</th></tr>\n\t<tr><td>x</td><td>y</td></tr>\n</table>\n",
		];
		yield 'coloured separators, indented, without header' => [
			"<tab>a <highlight>|<end> 10\n<tab>b <highlight>|<end> 200\ntext",
			"<indent>\n\t<table border=\"true\">\n\t\t<tr><td>a</td><td align=\"right\">10</td></tr>\n\t\t<tr><td>b</td><td align=\"right\">200</td></tr>\n\t</table>\n</indent>\n\ntext\n",
		];
		yield 'separators at the edges' => ["| a | b |\n| c | d |", "<table border=\"true\">\n\t<tr><td>a</td><td>b</td></tr>\n\t<tr><td>c</td><td>d</td></tr>\n</table>\n"];
		yield 'shorter rows span the rest' => [
			"a | b | c\nd | e | f\ng | h",
			"<table border=\"true\">\n\t<tr><td>a</td><td>b</td><td>c</td></tr>\n\t<tr><td>d</td><td>e</td><td>f</td></tr>\n\t<tr><td>g</td><td colspan=\"2\">h</td></tr>\n</table>\n",
		];
		yield 'one line is no table' => ["only one | line", "only one | line\n"];
		yield 'only one row with the most columns' => ["a | b | c\nd | e", "a | b | c\nd | e\n"];
		yield 'bars are no separators' => ["bar ||||| x\nbar || y", "bar ||||| x\nbar || y\n"];
		yield 'rows must be consecutive' => ["a | b\nplain line\nc | d", "a | b\nplain line\nc | d\n"];
	}

	#[DataProvider('provideTables')]
	public function testTables(string $bbml, string $aoxml): void {
		$logger = TestLogger::create();
		$this->assertSame($aoxml, self::serialize((new BbmlImporter($logger, detectTables: true))->import($bbml)));
		$this->assertSame([], $logger->records->all());
	}

	public function testTablesAreOnlyDetectedWhenAskedFor(): void {
		$this->assertSame("a | b\nc | d\n", self::serialize((new BbmlImporter())->import("a | b\nc | d")));
	}

	public function testPlainAomlWithTheBotsName(): void {
		$aoml = '<a href="text://<font color=#89D2E8><font color=#FFFF00>Help</font>' . "\n\n"
			. "<a href='chatcmd:///tell Nadybot !aggdef 1.2'>/tell Nadybot !aggdef 1.2</a> <a href='chatcmd:///tell Nadybot !help'>!help</a>"
			. '">Help</a>';
		$this->assertSame(
			"<popup window=\"window1\">Help</popup>\n\n<window id=\"window1\" title=\"Help\">\n\t<cmd show=\"tell\">aggdef 1.2</cmd> <cmd>help</cmd>\n</window>\n",
			self::serialize((new BbmlImporter(botName: 'Nadybot', symbol: '!'))->import($aoml))
		);
		$this->assertStringContainsString('<client command="/tell Nadybot !help">!help</client>', self::serialize((new BbmlImporter())->import($aoml)));
	}

	private static function serialize(\AO\Markup\Document $document): string {
		return (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
	}
}
