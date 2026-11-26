<?php declare(strict_types=1);

namespace AO\Markup\Tests\Export;

use function Safe\{file_get_contents, glob};

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\AoXmlSerializer;
use AO\Markup\Node\{Fragment, Paragraph, Text};
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\TreeDump;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AoXmlSerializerTest extends TestCase {
	/** @return iterable<string,array{string}> */
	public static function provideSamples(): iterable {
		foreach (glob(__DIR__ . '/../Samples/*/input.aoxml') as $file) {
			yield basename(dirname($file)) => [$file];
		}
	}

	/** The sample inputs are written in canonical form */
	#[DataProvider('provideSamples')]
	public function testSamplesAreCanonical(string $file): void {
		$input = file_get_contents($file);
		$this->assertSame($input, self::serialize(self::parse($input)));
	}

	/** @return iterable<string,array{string}> */
	public static function provideRoundTrips(): iterable {
		yield 'escapes' => ['a &lt;b&gt; &amp; "c" \'d\''];
		yield 'attribute escapes' => ['<url href="https://x.org/?a=1&amp;b=&quot;2&quot;">x</url>'];
		yield 'line breaks' => ["one\ntwo\n\nthree"];
		yield 'break at start and end' => ['<br>a<br>'];
		yield 'several breaks' => ["a<br><br>b\n<br>c"];
		yield 'breaks next to spaces' => ['a <br>b<br> c'];
		yield 'breaks in elements' => ['<i>a<br></i><br><strong><br>b</strong>'];
		yield 'breaks in cells' => ['<table><tr><td><br>x</td></tr></table><dl><dt>a<br></dt><dd><br></dd></dl>'];
		yield 'styles' => ['<strong>a <i>b</i></strong> <u>c</u> <match>d</match> <selected>e</selected> <syntax>f</syntax> <green>g</green>'];
		yield 'colour' => ['<color value="#ABCDEF">x</color>'];
		yield 'items' => ['<item id="1/2" ql="3">x</item> <item id="5" ql="1">y</item>'];
		yield 'nano and skill' => ['<nano id="12">n</nano> <skill id="51">s</skill>'];
		yield 'chars' => ['<char>Nady</char> <char name="Nady">she</char>'];
		yield 'commands' => ['<cmd>help</cmd> <cmd show="tell" via="priv">demo</cmd> <cmd command="help x" via="org">Help</cmd>'];
		yield 'client commands' => ['<client>/assist Nady</client> <client command="/macro a /assist a">M</client>'];
		yield 'waypoint' => ['<waypoint x="1" y="2" pf="585">Camp</waypoint>'];
		yield 'images' => ['<img src="rdb:123"/> <img src="tdb:GFX_X"/>'];
		yield 'placeholders' => ['<myname/> <myguild/> <symbol/> <var name="x"/>'];
		yield 'section' => ["<section title=\"A &quot;B&quot;\">\n\tx\n\n\t<section title=\"C\">y</section>\n</section>"];
		yield 'field list' => ['<dl><dt>A</dt><dd unit="s">1</dd><dt><strong>B</strong></dt><dd>2</dd><dd>3</dd></dl>'];
		yield 'table' => ['<table border><tr><th align="right">H</th><th>I</th></tr><tr><td colspan="2">x<br>y</td></tr></table>'];
		yield 'layout table' => ['<table><tr><td>a</td><td><ul><li>b</li></ul></td></tr></table>'];
		yield 'lists' => ['<ol start="3"><li icon="rdb:1">a</li><li>b<ul><li>c</li></ul></li></ol>'];
		yield 'blocks' => ['<h1>T</h1><h2>U</h2><hr/><center>c</center><keep>k</keep><repeat>r</repeat><pagebreak/><indent>i</indent>'];
		yield 'windows' => ['See <popup window="a"><strong>2</strong> bans</popup>.<window id="a" title="A">x<footer>Data by\nAuno</footer></window>'];
		yield 'empty window' => ['<window id="a" title="A"></window>'];
		yield 'widgets' => ['<slider pos="73.5" min="10" left="L"/> x <unavailable/> <syntax>a <param>b</param></syntax>'];
		yield 'choice' => ['<choice command="settings save x" via="priv" inline><option value="0" selected>No</option><option value="1"><strong>Yes</strong></option></choice>'];
	}

	/** Parsing the canonical form gives the same tree, and the canonical form is stable */
	#[DataProvider('provideRoundTrips')]
	public function testRoundTrip(string $input): void {
		$logger = TestLogger::create();
		$document = self::parse($input, $logger);
		$this->assertSame([], array_map(static fn ($r): string => (string)$r->message, $logger->records->all()), 'The input itself must be valid');

		$canonical = self::serialize($document);
		$reparsed = self::parse($canonical, $logger);
		$this->assertSame(TreeDump::document($document), TreeDump::document($reparsed), "Canonical form:\n{$canonical}");
		$this->assertSame($canonical, self::serialize($reparsed));
		$this->assertSame([], array_map(static fn ($r): string => (string)$r->message, $logger->records->all()));
	}

	public function testCanonicalForm(): void {
		$this->assertSame(
			<<<'AOXML'
				<popup window="a">Open</popup>

				<window id="a" title="A">
					<table border="true">
						<tr><th align="right">H</th></tr>
						<tr>
							<td>
								one
								two
							</td>
						</tr>
					</table>

					<choice command="x">
						<option value="1" selected="true">One</option>
					</choice>
				</window>

				AOXML,
			self::serialize(self::parse(
				'<window id="a" title="A"><table border><tr><th align="right">H</th></tr>'
				. "<tr><td>one\ntwo</td></tr></table><choice command=\"x\"><option value=\"1\" selected>One</option></choice></window>"
				. '<popup window="a">Open</popup>'
			))
		);
	}

	public function testBuiltTree(): void {
		$document = new Document(new Fragment(new Paragraph(new Text('a < b'))));
		$this->assertSame("a &lt; b\n", self::serialize($document));
	}

	private static function parse(string $input, ?TestLogger $logger=null): Document {
		return (new AoXmlParser(WidgetRegistry::withBuiltins(), $logger ?? TestLogger::create()))->parse($input);
	}

	private static function serialize(Document $document): string {
		return (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
	}
}
