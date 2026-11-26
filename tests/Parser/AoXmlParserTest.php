<?php declare(strict_types=1);

namespace AO\Markup\Tests\Parser;

use function Safe\{file_get_contents, glob};

use AO\Markup\Document;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\TreeDump;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AoXmlParserTest extends TestCase {
	private TestLogger $logger;

	protected function setUp(): void {
		$this->logger = TestLogger::create();
	}

	/** @return iterable<string,array{string}> */
	public static function provideSamples(): iterable {
		foreach (glob(__DIR__ . '/../Samples/*/input.aoxml') as $file) {
			yield basename(dirname($file)) => [$file];
		}
	}

	#[DataProvider('provideSamples')]
	public function testSamplesParseWithoutWarnings(string $file): void {
		$this->parse(file_get_contents($file));
		$this->assertNoWarnings();
	}

	// ------------------------------------------------------------------
	// Whitespace
	// ------------------------------------------------------------------

	public function testLineBreaksAndParagraphs(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "one two"
				  LineBreak
				  Text "three"
				Paragraph
				  Text "four"
				TREE,
			"\n\t\t  one   two  \n\t\tthree\n   \n\n\tfour\n\n"
		);
		$this->assertNoWarnings();
	}

	public function testSpacesCollapseAcrossElements(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "a "
				  Style strong
				    Text "b"
				  Text " c"
				TREE,
			'a <strong>b </strong> c'
		);
	}

	public function testLineBreaksAroundBlocksAreNotSignificant(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "Intro"
				Indent
				  Paragraph
				    Text "inner"
				Paragraph
				  Text "Outro"
				TREE,
			"Intro\n<indent>\n\tinner\n</indent>\nOutro"
		);
	}

	public function testEmptyLineInsideInlineElementIsTwoLineBreaks(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Style i
				    Text "a"
				    LineBreak
				    LineBreak
				    Text "b"
				TREE,
			"<i>a\n\nb</i>"
		);
	}

	// ------------------------------------------------------------------
	// Syntax and recovery
	// ------------------------------------------------------------------

	public function testEntities(): void {
		$this->assertTree('Paragraph' . "\n" . '  Text "<a> & \"b\" \' é €"', '&lt;a&gt; &amp; &quot;b&quot; &apos; &#233; &#x20AC;');
		$this->assertNoWarnings();
	}

	public function testBareAmpersandAndLessThanAreText(): void {
		$this->assertTree('Paragraph' . "\n" . '  Text "Q&A < 3 &foo;"', 'Q&A < 3 &foo;');
		$this->assertWarning('Unescaped "&"');
		$this->assertWarning('Unescaped "<"');
		$this->assertWarning('Unknown entity &foo;');
	}

	public function testCommentsAreDropped(): void {
		$this->assertTree('Paragraph' . "\n" . '  Text "a b"', 'a <!-- comment --> b');
	}

	public function testUnknownElementIsText(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "<blink>"
				  Style strong
				    Text "x"
				  Text "</blink>"
				TREE,
			'<blink><strong>x</strong></blink>'
		);
		$this->assertWarning('Unknown element <blink>');
	}

	public function testUnclosedElementIsClosedAtParent(): void {
		$this->assertTree(
			<<<'TREE'
				Section title="S"
				  Paragraph
				    Style strong
				      Text "x"
				Paragraph
				  Text "after"
				TREE,
			'<section title="S"><strong>x</section>after'
		);
		$this->assertWarning('<strong> is not closed');
	}

	public function testStrayClosingTagIsIgnored(): void {
		$this->assertTree('Paragraph' . "\n" . '  Text "ab"', 'a</green>b');
		$this->assertWarning('there is no open <green>');
	}

	public function testVoidElementsWithoutSlash(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "a"
				  LineBreak
				  Text "b"
				HorizontalRule
				Paragraph
				  Placeholder name="myname"
				TREE,
			'a<br>b<hr><myname>'
		);
		$this->assertNoWarnings();
	}

	public function testUnknownAttributeIsReported(): void {
		$this->parse('<section title="S" colour="red">x</section>');
		$this->assertWarning('Unknown attribute "colour" on <section>');
	}

	// ------------------------------------------------------------------
	// Attributes
	// ------------------------------------------------------------------

	public function testBooleanAttribute(): void {
		$this->assertSame('Table border=true', $this->tree('<table border></table>'));
		$this->assertSame('Table border=true', $this->tree('<table border="true"></table>'));
		$this->assertSame('Table', $this->tree('<table border="false"></table>'));
		$this->assertNoWarnings();

		$this->assertSame('Table border=true', $this->tree('<table border=true></table>'));
		$this->assertWarning('Unquoted value of attribute "border"');
	}

	public function testItemIds(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  ItemLink lowId=246800 highId=246801 ql=100
				    Text "Kit"
				  Text " "
				  ItemLink lowId=247115 highId=247115 ql=1
				    Text "Kit"
				TREE,
			'<item id="246800/246801" ql="100">Kit</item> <item id="247115" ql="1">Kit</item>'
		);
		$this->assertNoWarnings();
	}

	public function testItemWithoutQl(): void {
		$this->assertTree(
			"Paragraph\n  ItemLink lowId=1 highId=2 ql=1\n    Text \"Kit\"",
			'<item id="1/2">Kit</item>'
		);
		$this->assertWarning('<item> without ql, using QL 1');
	}

	public function testInvalidItemKeepsText(): void {
		$this->assertTree("Paragraph\n  Text \"Kit\"", '<item ref="1/2/3">Kit</item>');
		$this->assertWarning('Invalid id=""');
		$this->assertWarning('Unknown attribute "ref" on <item>');
	}

	public function testColor(): void {
		$this->assertTree("Paragraph\n  Color hex=\"#ABCDEF\"\n    Text \"x\"", '<color value="#abcdef">x</color>');
		$this->assertTree("Paragraph\n  Text \"x\"", '<color value="red">x</color>');
		$this->assertWarning('Invalid colour "red"');
	}

	// ------------------------------------------------------------------
	// Elements
	// ------------------------------------------------------------------

	public function testCommands(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  CommandLink command="help syntax"
				  Text " "
				  CommandLink command="aggdef 1 2 3" show=tell
				  Text " "
				  CommandLink command="help"
				    Text "Help"
				TREE,
			'<cmd>help  syntax</cmd> <cmd show="tell">aggdef 1 2 3</cmd> <cmd command="help">Help</cmd>'
		);
		$this->assertNoWarnings();
	}

	public function testCommandChannels(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  CommandLink command="demo" via=priv
				  Text " "
				  CommandLink command="demo" via=org
				    Text "Demo"
				TREE,
			'<cmd via="priv">demo</cmd> <cmd via="org" command="demo">Demo</cmd>'
		);
		$this->parse('<cmd via="raid">x</cmd>');
		$this->assertWarning('Invalid via="raid"');
	}

	public function testClientCommands(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  ClientCommandLink command="/assist Nady"
				  Text " "
				  ClientCommandLink command="/macro Nady /assist Nady"
				    Text "Macro"
				TREE,
			'<client>/assist Nady</client> <client command="/macro Nady /assist Nady">Macro</client>'
		);
		$this->assertNoWarnings();
	}

	public function testAttributesArePlainText(): void {
		$this->assertTree(
			"Paragraph\n  ClientCommandLink command=\"/g <myname> hi\"\n    Text \"x\"",
			'<client command="/g &lt;myname&gt; hi">x</client>'
		);
	}

	public function testWaypoints(): void {
		$this->assertTree(
			"Paragraph\n  WaypointLink x=123 y=456 playfield=585\n    Text \"Camp\"",
			'<waypoint x="123" y="456" pf="585">Camp</waypoint>'
		);
		$this->assertNoWarnings();
		$this->assertTree("Paragraph\n  Text \"Camp\"", '<waypoint x="123" pf="585">Camp</waypoint>');
		$this->assertWarning('<waypoint> needs numeric x, y and pf');
	}

	public function testHintsForForeignElements(): void {
		$this->parse('<a href="text://x">x</a> <highlight>y</highlight>');
		$this->assertWarning('Unknown element <a> taken as text; AoXML has no <a>');
		$this->assertWarning('Unknown element <highlight> taken as text; use <strong>');
	}

	public function testChars(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  CharLink name="Nady"
				  Text " "
				  CharLink name="Nady"
				  Text " "
				  CharLink name="Nady"
				    Text "she"
				TREE,
			'<char>Nady</char> <char name="Nady"/> <char name="Nady">she</char>'
		);
		$this->assertNoWarnings();
	}

	public function testImages(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Image source=rdb id="12345"
				  Image source=tdb id="GFX_GUI_ICON"
				TREE,
			'<img src="rdb:12345"><img src="tdb:GFX_GUI_ICON"/><img src="rdb:x">'
		);
		$this->assertWarning('Invalid image "rdb:x"');
	}

	public function testTable(): void {
		$this->assertTree(
			<<<'TREE'
				Table
				  TableRow
				    TableCell header=true align=right
				      Paragraph
				        Text "H"
				  TableRow
				    TableCell colspan=2
				      Paragraph
				        Text "D"
				TREE,
			"<table>\n\t<tr><th align=\"right\">H</th></tr>\n\t<tr>\n\t\t<td colspan=\"2\">D</td>\n\t</tr>\n</table>"
		);
		$this->assertNoWarnings();
	}

	public function testFieldLists(): void {
		$this->assertTree(
			<<<'TREE'
				FieldList
				  Field
				    .name
				      Text "Attack"
				    FieldValue unit="second(s)"
				      Text "1.2"
				  Field
				    .name
				      Style strong
				        Text "Nady"
				      Text "'s alts"
				    FieldValue
				      Text "Nadyita"
				    FieldValue
				      Text "Nadybot"
				TREE,
			"<dl>\n\t<dt>Attack</dt><dd unit=\"second(s)\">1.2</dd>\n\t<dt><strong>Nady</strong>'s alts</dt>\n\t<dd>Nadyita</dd>\n\t<dd>Nadybot</dd>\n</dl>"
		);
		$this->assertNoWarnings();
	}

	public function testFieldListErrors(): void {
		$this->assertTree(
			<<<'TREE'
				FieldList
				  Field
				    .name
				    FieldValue
				      Text "orphan"
				    FieldValue
				      Text "stray"
				  Field
				    .name
				      Text "empty"
				TREE,
			'<dl><dd>orphan</dd>stray<dt>empty</dt></dl>'
		);
		$this->assertWarning('<dl> must start with a <dt>');
		$this->assertWarning('outside of <dt> or <dd>');
		$this->assertWarning('<dt> without <dd>');
	}

	public function testFieldOutsideList(): void {
		$this->assertTree("Paragraph\n  Text \"x\"", '<dd>x</dd>');
		$this->assertWarning('<dd> outside of <dl>');
	}

	public function testStrayContentInTableIsWrapped(): void {
		$this->assertTree(
			<<<'TREE'
				Table
				  TableRow
				    TableCell
				      Paragraph
				        Text "x"
				TREE,
			'<table><tr>x</tr></table>'
		);
		$this->assertWarning('outside of <td> or <th>');
	}

	public function testInvalidAlign(): void {
		$this->parse('<table><tr><td align="middle">x</td></tr></table>');
		$this->assertWarning('Invalid align="middle"');
	}

	public function testLists(): void {
		$this->assertTree(
			<<<'TREE'
				ListBlock ordered=true start=3
				  ListItem
				    Paragraph
				      Text "a"
				  ListItem icon=Image source=rdb id="1"
				    Paragraph
				      Text "b"
				    ListBlock
				      ListItem
				        Paragraph
				          Text "c"
				TREE,
			"<ol start=\"3\">\n<li>a</li>\n<li icon=\"rdb:1\">b\n<ul><li>c</li></ul>\n</li>\n</ol>"
		);
		$this->assertNoWarnings();
	}

	public function testBlockElementInsideLine(): void {
		$this->assertTree("Paragraph\n  Style strong\n    Text \"x\"", '<strong><section title="S">x</section></strong>');
		$this->assertWarning('Block element <section> inside a line');
	}

	public function testPlaceholders(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Placeholder name="myname"
				  Placeholder name="myguild"
				  Placeholder name="symbol"
				  Placeholder name="color"
				TREE,
			'<myname/><myguild/><symbol/><var name="color"/>'
		);
		$this->assertNoWarnings();
	}

	// ------------------------------------------------------------------
	// Popups
	// ------------------------------------------------------------------

	public function testWindowsAndPopups(): void {
		$doc = $this->parse(
			"There are <popup window=\"bans\"><strong>2</strong> bans</popup>, see <popup window=\"bans\">here</popup>.\n"
			. "<window id=\"bans\" title=\"Banlist (2)\">\n\tx\n</window>\n"
			. '<window id="help" title="Help">y</window>'
		);
		$this->assertSame(
			<<<'TREE'
				Paragraph
				  Text "There are "
				  PopupLink window="bans"
				    Style strong
				      Text "2"
				    Text " bans"
				  Text ", see "
				  PopupLink window="bans"
				    Text "here"
				  Text "."
				Window bans "Banlist (2)"
				  Paragraph
				    Text "x"
				Window help "Help"
				  Paragraph
				    Text "y"
				TREE,
			TreeDump::document($doc)
		);
		$this->assertNoWarnings();
	}

	public function testPopupInsideWindowIsText(): void {
		$doc = $this->parse('<window id="a" title="A">See <popup window="b">B</popup></window><window id="b" title="B">b</window>');
		$this->assertSame("Window a \"A\"\n  Paragraph\n    Text \"See B\"\nWindow b \"B\"\n  Paragraph\n    Text \"b\"", TreeDump::document($doc));
		$this->assertWarning('Windows cannot be nested');
	}

	public function testWindowInsideElementIsUsedWithWarning(): void {
		$doc = $this->parse('<section title="S"><window id="a" title="A">a</window></section><strong><window id="b" title="B">b</window></strong>');
		$this->assertSame(['a', 'b'], array_keys($doc->windows));
		$this->assertWarning('<window> is only allowed at the top level');
	}

	public function testWindowErrors(): void {
		$doc = $this->parse('<window title="A">a</window><window id="x" title="X">1</window><window id="x" title="Y">2</window>');
		$this->assertSame(['x'], array_keys($doc->windows));
		$this->assertSame('X', $doc->windows['x']->title);
		$this->assertWarning('<window> without id cannot be opened');
		$this->assertWarning('Duplicate window id "x"');
	}

	public function testFooterGoesToTheEnd(): void {
		$doc = $this->parse(
			"<window id=\"w\" title=\"W\">\n\t<footer>Data by <strong>Auno</strong></footer>\n\tText\n"
			. "\t<section title=\"S\"><footer>Based on\nRINGBOT</footer>x</section>\n</window>"
		);
		$this->assertSame(
			<<<'TREE'
				Window w "W"
				  Paragraph
				    Text "Text"
				  Section title="S"
				    Paragraph
				      Text "x"
				  Footer
				    Text "Data by "
				    Style strong
				      Text "Auno"
				    LineBreak
				    Text "Based on"
				    LineBreak
				    Text "RINGBOT"
				TREE,
			TreeDump::document($doc)
		);
		$this->assertNoWarnings();
	}

	public function testFooterOutsideWindow(): void {
		$this->assertTree("Paragraph\n  Text \"Credits\"", '<footer>Credits</footer>');
		$this->assertWarning('<footer> is only allowed inside a <window>');
	}

	public function testMissingWindow(): void {
		$this->assertTree("Paragraph\n  PopupLink window=\"nope\"\n    Text \"x\"", '<popup window="nope">x</popup>');
		$this->assertWarning('<popup window="nope"> refers to a window that does not exist');
	}

	public function testOldPopupFormIsRejected(): void {
		$this->parse('<popup title="Old">content</popup>');
		$this->assertWarning('<popup> needs the attribute "window"');
		$this->assertWarning('Unknown attribute "title" on <popup>');
	}

	// ------------------------------------------------------------------
	// Widgets
	// ------------------------------------------------------------------

	public function testBlockWidgetIsNotWrappedInParagraph(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Text "a"
				WidgetNode Slider pos=50 min=10
				Paragraph
				  Text "b"
				TREE,
			"a\n<slider pos=\"50\" min=\"10\"/>\nb"
		);
		$this->assertNoWarnings();
	}

	public function testWidgetWithMissingAttributeIsDropped(): void {
		$this->assertTree('', '<slider max="10"/>');
		$this->assertWarning('<slider> needs the attribute "pos"');
		$this->assertWarning('<slider> dropped');
	}

	public function testWidgetAttributeErrors(): void {
		$this->assertTree('WidgetNode Slider pos=5', '<slider pos="5" min="low" colour="red"/>');
		$this->assertWarning('invalid value "low" for attribute "min"');
		$this->assertWarning('<slider> has no attribute "colour"');
	}

	public function testChoice(): void {
		$this->assertTree(
			<<<'TREE'
				WidgetNode Choice command="settings save x" via=priv
				  WidgetNode Option value="0" selected=true
				    Text "No"
				  WidgetNode Option value="1"
				    Style strong
				      Text "Yes"
				TREE,
			"<choice command=\"settings save x\" via=\"priv\">\n\t<option value=\"0\" selected>No</option>\n\t<option value=\"1\"><strong>Yes</strong></option>\n</choice>"
		);
		$this->assertNoWarnings();
	}

	public function testChoiceDropsOtherContent(): void {
		$this->parse('<choice command="x">text<option value="1">A</option><strong>B</strong></choice>');
		$this->assertWarning('<choice> may only contain <option>, dropping the rest');
	}

	public function testInlineWidgetWithContent(): void {
		$this->assertTree(
			<<<'TREE'
				Paragraph
				  Style syntax
				    Text "cmd "
				    WidgetNode Param
				      Text "arg"
				TREE,
			'<syntax>cmd <param>arg</param></syntax>'
		);
	}

	// ------------------------------------------------------------------

	private function parse(string $input): Document {
		return (new AoXmlParser(WidgetRegistry::withBuiltins(), $this->logger))->parse($input);
	}

	private function tree(string $input): string {
		return TreeDump::document($this->parse($input));
	}

	private function assertTree(string $expected, string $input): void {
		$this->assertSame($expected, $this->tree($input));
	}

	private function assertNoWarnings(): void {
		$messages = array_map(static fn ($r): string => (string)$r->message, $this->logger->records->all());
		$this->assertSame([], $messages);
	}

	private function assertWarning(string $needle): void {
		$messages = array_map(static fn ($r): string => (string)$r->message, $this->logger->records->all());
		$this->assertTrue(
			$this->logger->records->includeMessagesContaining($needle),
			"No warning containing '{$needle}', got:\n" . implode("\n", $messages)
		);
	}
}
