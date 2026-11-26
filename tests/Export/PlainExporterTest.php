<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Export\{AnsiExporter, Exporter, PlainExporter};
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\AnsiNotation;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Cases the samples don't cover; the samples are in SamplesTest */
final class PlainExporterTest extends TestCase {
	/** @return iterable<string,array{string,string}> */
	public static function provideCases(): iterable {
		yield 'layout table with multi-line cell' => [
			'<table><tr><td>a</td><td>bb<br>c</td><td>d</td></tr><tr><td>long cell</td><td align="right">x</td><td>y</td></tr></table>',
			"a          bb  d\n           c\nlong cell   x  y\n",
		];
		yield 'colspan wider than its columns' => [
			'<table border><tr><th>A</th><th>B</th></tr><tr><td colspan="2">a long spanning cell</td></tr><tr><td>1</td><td>2</td></tr></table>',
			"A │ B\n──┼─────────────────\na long spanning cell\n1 │ 2\n",
		];
		yield 'centred cell' => [
			'<table><tr><td align="center">x</td><td>|</td></tr><tr><td>abcde</td><td>|</td></tr></table>',
			"  x    |\nabcde  |\n",
		];
		yield 'nested and numbered lists' => [
			'<ol start="9"><li>a<ul><li>b</li><li>c<br>d</li></ul></li><li>e</li></ol>',
			" 9. a\n    • b\n    • c\n      d\n10. e\n",
		];
		yield 'several values per field' => [
			'<dl><dt>Key</dt><dd>one</dd><dd>two<br>three</dd><dt>K2</dt></dl>',
			"Key: one\n     two\n     three\nK2:\n",
		];
		yield 'urls' => [
			'See <url href="https://x.org">X</url> and <url href="https://y.org">https://y.org</url>',
			"See X (https://x.org) and https://y.org\n",
		];
		yield 'commands' => [
			'<cmd show="tell" via="priv">a</cmd> <cmd show="tell" via="org">b</cmd> <cmd command="c">C</cmd> <char>Nady</char> <client>/assist Nady</client>',
			"/g Bot !a /o !b C Nady /assist Nady\n",
		];
		yield 'message with a window' => [
			'See <popup window="a">this</popup>.<window id="a" title="A">x<hr/><h1>H</h1></window>',
			"See this.\n\nA\n\nx\n\n" . str_repeat('─', 40) . "\n\nH\n",
		];
		yield 'pagination hints are invisible' => [
			'<keep>a</keep><pagebreak/><repeat>b</repeat>',
			"a\n\nb\n",
		];
		yield 'indent continues a paragraph only' => [
			"a\n\n<indent>b</indent>\n\n<indent>c</indent>",
			"a\n    b\n\n    c\n",
		];
		yield 'inline choice' => [
			'<choice command="x" inline><option value="1">One</option><option value="2" selected>Two</option></choice>',
			"One [Two]\n",
		];
		yield 'empty document' => ['', ''];
	}

	#[DataProvider('provideCases')]
	public function testPlain(string $input, string $expected): void {
		$this->assertSame($expected, self::export(new PlainExporter(), $input));
	}

	public function testAnsiRestoresTheEnclosingColour(): void {
		$this->assertSame(
			AnsiNotation::toEscapes("{#00DE42}a {#FF0000}b{/} c{/} {u}{#219CFF}!<cmd>{/}{/u}\n"),
			self::export(new AnsiExporter(), '<green>a <red>b</red> c</green> <cmd>&lt;cmd&gt;</cmd>')
		);
	}

	public function testAnsiUrlsAreHyperlinks(): void {
		$this->assertSame(
			AnsiNotation::toEscapes("{link https://x.org}{u}{#219CFF}X{/}{/u}{/link}\n"),
			self::export(new AnsiExporter(), '<url href="https://x.org">X</url>')
		);
	}

	public function testBulletIsConfigurable(): void {
		$this->assertSame("- a\n", self::export(new PlainExporter(bullet: '-'), '<ul><li>a</li></ul>'));
	}

	public function testMissingPlaceholdersAreLogged(): void {
		$logger = TestLogger::create();
		$this->assertSame("a  b\n", self::export(new PlainExporter(), 'a <var name="nope"/> b', $logger));
		$this->assertCount(1, $logger->records->all());
	}

	private static function export(Exporter $exporter, string $input, ?TestLogger $logger=null): string {
		$logger ??= TestLogger::create();
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger))->parse($input);
		$context = new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger);
		return $exporter->export($document, $context);
	}
}
