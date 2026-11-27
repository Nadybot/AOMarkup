<?php declare(strict_types=1);

namespace AO\Markup\Tests\Builder;

use AO\Markup\Build as B;
use AO\Markup\Builder\InlineBuilder;
use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\AoXmlSerializer;
use AO\Markup\Node\{CommandChannel, CommandDisplay, Fragment, StyleName, Text};
use AO\Markup\Tests\Support\TreeDump;
use AO\Markup\Widget\Builtin\Slider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use TypeError;

final class BuildTest extends TestCase {
	public function testCount(): void {
		$this->assertSame('0 bans', B::count(0, 'ban'));
		$this->assertSame('1 ban', B::count(1, 'ban'));
		$this->assertSame('2 bans', B::count(2, 'ban'));
		$this->assertSame('1 child', B::count(1, 'child', 'children'));
		$this->assertSame('3 children', B::count(3, 'child', 'children'));
		$this->assertSame('1.5 hours', B::count(1.5, 'hour'));
	}

	public function testContentTypes(): void {
		$stringable = new class () implements Stringable {
			public function __toString(): string {
				return 'S';
			}
		};
		$this->assertSame('a 1 2.5 S <strong>x</strong> <i>y</i>', self::inline(
			B::text('a ', 1, ' ', 2.5, ' ', $stringable, null, ' ', B::strong('x'), ' ', B::i('y'))
		));
	}

	public function testAdjacentTextsAreJoined(): void {
		$this->assertSame("Paragraph\n  Text \"ab1\"", TreeDump::nodes(B::p('a', 'b', null, 1)->build()));
	}

	public function testNewlineIsALineBreak(): void {
		$this->assertSame("Paragraph\n  Text \"a\"\n  LineBreak\n  Text \"b\"", TreeDump::nodes(B::p("a\nb")->build()));
	}

	public function testFragmentsAreFlattened(): void {
		$this->assertSame('ab', self::inline(B::text(new Fragment(new Text('a'), new Fragment(new Text('b'))))));
		$this->assertInstanceOf(Text::class, B::text('a', 'b')->build());
		$this->assertInstanceOf(Fragment::class, B::text('a', B::strong('b'))->build());
	}

	public function testEveryArgumentOfAddIsABlock(): void {
		$this->assertSame(
			"<section title=\"T\">\n\ta\n\n\t<strong>b</strong>\n\n\tc <i>d</i>\n\n\t<hr/>\n</section>\n",
			self::aoxml(B::document(B::section('T')->add('a', null, B::strong('b'), B::p('c ', B::i('d')), B::hr())))
		);
	}

	public function testBlockWidgetIsNoParagraph(): void {
		$section = B::section('T')->add(B::slider(30), B::widget(new Slider(40)))->build();
		$this->assertSame(
			"Section title=\"T\"\n  WidgetNode Slider pos=30\n  WidgetNode Slider pos=40",
			TreeDump::nodes($section)
		);
	}

	public function testInlineWidgetIsInAParagraph(): void {
		$this->assertSame(
			"<indent>\n\t<syntax>ban <param>name</param></syntax> <unavailable/>\n</indent>\n",
			self::aoxml(B::document(B::indent(B::text(B::syntax('ban ', B::param('name')), ' ', B::unavailable()))))
		);
	}

	public function testStyles(): void {
		$this->assertSame(
			'<header>a</header><header2>b</header2><on>c</on><off>d</off><neutral>e</neutral><omni>f</omni>'
			. '<clan>g</clan><unknown>h</unknown><match>i</match><selected>j</selected><syntax>k</syntax>'
			. '<u>l</u><i>m</i><black>n</black><white>o</white><yellow>p</yellow><blue>q</blue>'
			. '<green>r</green><red>s</red><orange>t</orange><grey>u</grey><cyan>v</cyan><violet>w</violet>',
			self::inline(B::text(
				B::header('a'),
				B::header2('b'),
				B::on('c'),
				B::off('d'),
				B::neutral('e'),
				B::omni('f'),
				B::clan('g'),
				B::unknown('h'),
				B::match('i'),
				B::selected('j'),
				B::syntax('k'),
				B::u('l'),
				B::i('m'),
				B::black('n'),
				B::white('o'),
				B::yellow('p'),
				B::blue('q'),
				B::green('r'),
				B::red('s'),
				B::orange('t'),
				B::grey('u'),
				B::cyan('v'),
				B::violet('w'),
			))
		);
	}

	public function testStyleByName(): void {
		$this->assertSame('<clan>a</clan><strong>b</strong>c', self::inline(B::text(
			B::style('clan', 'a'),
			B::style(StyleName::Strong, 'b'),
			B::style('nonsense', 'c'),
		)));
	}

	public function testColor(): void {
		$this->assertSame('<color value="#ABCDEF">a</color><color value="#123456">b</color>c', self::inline(B::text(
			B::color('#abcdef', 'a'),
			B::color('123456', 'b'),
			B::color('blue', 'c'),
		)));
	}

	public function testGameObjectsAndLinks(): void {
		$this->assertSame(
			'<item id="1" ql="1">a</item> <item id="1/2" ql="3">b</item> <nano id="5">c</nano> <skill id="6">d</skill> '
			. '<char>Nady</char> <char name="Nady">e</char> <waypoint x="1" y="2" pf="585">f</waypoint> '
			. '<cmd>help</cmd> <cmd command="help" show="tell" via="priv">g</cmd> '
			. '<client>/assist Nady</client> <client command="/macro">h</client> <url href="https://x.org">i</url> '
			. '<img src="rdb:12"/><img src="tdb:GFX_X"/> <myname/><myguild/><symbol/><var name="x"/><br/>',
			self::inline(B::text(
				B::item(1, label: 'a'),
				' ',
				B::item(1, 2, 3, 'b'),
				' ',
				B::nano(5, 'c'),
				' ',
				B::skill(6, 'd'),
				' ',
				B::char('Nady'),
				' ',
				B::char('Nady', 'e'),
				' ',
				B::waypoint(1, 2, 585, 'f'),
				' ',
				B::cmd('help'),
				' ',
				B::cmd('help', 'g', show: CommandDisplay::Tell, via: CommandChannel::Priv),
				' ',
				B::client('/assist Nady'),
				' ',
				B::client('/macro', 'h'),
				' ',
				B::url('https://x.org', 'i'),
				' ',
				B::img('rdb:12'),
				B::img('tdb:GFX_X'),
				B::img('rdb:x'),
				B::img('nope'),
				' ',
				B::myname(),
				B::myguild(),
				B::symbol(),
				B::var('x'),
				B::br(),
			))
		);
	}

	public function testBlocks(): void {
		$this->assertSame(
			"<h1>A</h1>\n\n<h2>B</h2>\n\n<center>\n\tc\n</center>\n\n<keep>\n\tk\n</keep>\n\n"
			. "<repeat>\n\tr\n</repeat>\n\n<pagebreak/>\n\n<indent>\n\ti\n</indent>\n",
			self::aoxml(B::document(
				B::h1('A'),
				B::h2('B'),
				B::center('c'),
				B::keep('k'),
				B::repeat('r'),
				B::pagebreak(),
				B::indent('i'),
			))
		);
	}

	// ------------------------------------------------------------------
	// Short forms
	// ------------------------------------------------------------------

	public function testAddRow(): void {
		$table = B::table()
			->addHeaderRow('A', B::th('B', colspan: 2), B::td('C'))
			->addRow('a', 1, null, B::strong('x'), B::td('y'), B::th('z'), B::ul()->addItem('l'));
		$this->assertSame(
			"<table>\n"
			. "\t<tr><th>A</th><th colspan=\"2\">B</th><td>C</td></tr>\n"
			. "\t<tr>\n\t\t<td>a</td>\n\t\t<td>1</td>\n\t\t<td></td>\n\t\t<td><strong>x</strong></td>\n\t\t<td>y</td>\n\t\t<th>z</th>\n"
			. "\t\t<td>\n\t\t\t<ul>\n\t\t\t\t<li>l</li>\n\t\t\t</ul>\n\t\t</td>\n\t</tr>\n"
			. "</table>\n",
			self::aoxml(B::document($table))
		);
	}

	public function testAddRowIsTheLongForm(): void {
		$short = B::table(border: true)->addHeaderRow('A', 'B')->addRow('a', B::td('b', colspan: 2));
		$long = B::table(border: true)
			->add(B::tr(B::th('A'), B::th('B')))
			->add(B::tr()->add(B::td('a'), B::td('b', colspan: 2)));
		$this->assertSame(TreeDump::nodes($long->build()), TreeDump::nodes($short->build()));
	}

	public function testAddField(): void {
		$dl = B::dl()
			->addField('A', 1)
			->addField('B', null)
			->addField('C')
			->addField('D', null, 'x', B::dd('2', unit: 's'), null)
			->addField(B::strong('E'), B::strong('y'));
		$this->assertSame(
			"<dl>\n\t<dt>A</dt><dd>1</dd>\n\t<dt>D</dt><dd>x</dd><dd unit=\"s\">2</dd>\n\t<dt><strong>E</strong></dt><dd><strong>y</strong></dd>\n</dl>\n",
			self::aoxml(B::document($dl))
		);
	}

	public function testAddFieldIsTheLongForm(): void {
		$short = B::dl()->addField('Attack', B::dd('1.2', unit: 'second(s)'))->addField('Alts', 'Nadyita', 'Nadybot');
		$long = B::dl(
			B::dt('Attack')->add(B::dd('1.2', unit: 'second(s)')),
			B::dt('Alts')->add(B::dd('Nadyita'), B::dd('Nadybot')),
		);
		$this->assertSame(TreeDump::nodes($long->build()), TreeDump::nodes($short->build()));
	}

	public function testAddItem(): void {
		$list = B::ol(start: 3)
			->addItem('a ', B::strong('b'), null, ' c')
			->addItem(null)
			->addItem()
			->addItem('d', B::ul()->addItem('e'), 'f')
			->add(B::li('g', icon: 'rdb:1'), B::li(B::p('h'))->add('i'));
		$this->assertSame(
			"<ol start=\"3\">\n"
			. "\t<li>a <strong>b</strong> c</li>\n"
			. "\t<li>\n\t\td\n\n\t\t<ul>\n\t\t\t<li>e</li>\n\t\t</ul>\n\n\t\tf\n\t</li>\n"
			. "\t<li icon=\"rdb:1\">g</li>\n"
			. "\t<li>\n\t\th\n\n\t\ti\n\t</li>\n"
			. "</ol>\n",
			self::aoxml(B::document($list))
		);
	}

	public function testAddOption(): void {
		$short = B::choice('set x', via: CommandChannel::Org, inline: true)
			->addOption(0, 'No', selected: true)
			->addOption('1', B::green('Yes'));
		$long = B::choice('set x', via: CommandChannel::Org, inline: true)
			->add(B::option(0, 'No', selected: true), B::option('1', B::green('Yes')));
		$this->assertSame(TreeDump::nodes($long->build()), TreeDump::nodes($short->build()));
		$this->assertSame(
			"<choice command=\"set x\" via=\"org\" inline=\"true\">\n"
			. "\t<option value=\"0\" selected=\"true\">No</option>\n"
			. "\t<option value=\"1\"><green>Yes</green></option>\n"
			. "</choice>\n",
			self::aoxml(B::document($short))
		);
	}

	// ------------------------------------------------------------------
	// Documents and windows
	// ------------------------------------------------------------------

	public function testDocumentCollectsWindowsAndGeneratesIds(): void {
		$a = B::window('A')->add('a');
		$b = B::window('B', id: 'window1')->add('b');
		$c = B::window('C')->add('c');
		$document = B::document(
			'See ',
			$a->link(),
			', ',
			B::strong($b->link('the ', B::i('B'))),
			' and ',
			B::text($c->link(B::count(2, 'ban'))),
			'. Again: ',
			$a->link('A'),
			B::section('S')->add(B::p($c->link())),
		);
		$this->assertSame(['window2', 'window1', 'window3'], array_keys($document->windows));
		$this->assertSame(
			'See <popup window="window2">A</popup>, <strong><popup window="window1">the <i>B</i></popup></strong>'
			. ' and <popup window="window3">2 bans</popup>. Again: <popup window="window2">A</popup>' . "\n\n"
			. "<section title=\"S\">\n\t<popup window=\"window3\">C</popup>\n</section>\n\n"
			. "<window id=\"window2\" title=\"A\">\n\ta\n</window>\n\n"
			. "<window id=\"window1\" title=\"B\">\n\tb\n</window>\n\n"
			. "<window id=\"window3\" title=\"C\">\n\tc\n</window>\n",
			self::aoxml($document)
		);
	}

	public function testDuplicateIdsGetAGeneratedId(): void {
		$document = B::document(B::window('A', id: 'x')->link(), ' ', B::window('B', id: 'x')->link());
		$this->assertSame(['x' => 'A', 'window1' => 'B'], array_map(static fn ($w): string => $w->title, $document->windows));
	}

	public function testAsDocument(): void {
		$window = B::window('Bans')->add('x');
		$this->assertSame(
			"<popup window=\"window1\">Bans</popup>\n\n<window id=\"window1\" title=\"Bans\">\n\tx\n</window>\n",
			self::aoxml($window->asDocument())
		);
		$this->assertSame(
			"<popup window=\"window1\"><strong>2</strong> bans</popup>\n\n<window id=\"window1\" title=\"Bans\">\n\tx\n</window>\n",
			self::aoxml($window->asDocument(B::text(B::strong(2), ' bans')))
		);
	}

	public function testDocumentWithoutWindows(): void {
		$document = B::document();
		$this->assertCount(0, $document->body->children);
		$this->assertSame([], $document->windows);
		$this->assertSame("Paragraph\n  Text \"Hello\"", TreeDump::document(B::document('Hello')));
	}

	public function testBuildersAreBuiltOnBuild(): void {
		$dl = B::dl();
		$section = B::section('T')->add($dl);
		$dl->addField('A', 'a');
		$this->assertSame(
			"Section title=\"T\"\n  FieldList\n    Field\n      .name\n        Text \"A\"\n      FieldValue\n        Text \"a\"",
			TreeDump::nodes($section->build())
		);
		$window = B::window('W', id: 'w')->add('x');
		$this->assertSame('w', $window->build()->id);
		$this->assertSame('window1', B::window('W')->build()->id);
	}

	// ------------------------------------------------------------------
	// Type rules
	// ------------------------------------------------------------------

	/** @return iterable<string,array{object,string,mixed}> */
	public static function provideWrongChildren(): iterable {
		yield 'dd in dl' => [B::dl(), 'add', B::dd('x')];
		yield 'string in dl' => [B::dl(), 'add', 'x'];
		yield 'dt in dt' => [B::dt('x'), 'add', B::dt('y')];
		yield 'td in table' => [B::table(), 'add', B::td('x')];
		yield 'tr in tr' => [B::tr(), 'add', B::tr()];
		yield 'string in tr' => [B::tr(), 'add', 'x'];
		yield 'string in ul' => [B::ul(), 'add', 'x'];
		yield 'td in ol' => [B::ol(), 'add', B::td()];
		yield 'li in choice' => [B::choice('x'), 'add', B::li('x')];
		yield 'tr in section' => [B::section('x'), 'add', B::tr()];
		yield 'li in window' => [B::window('x'), 'add', B::li()];
		yield 'window in section' => [B::section('x'), 'add', B::window('x')];
		yield 'table in strong' => [B::strong(), 'add', B::table()];
		yield 'section in p' => [B::p(), 'add', B::section('x')];
		yield 'section in footer' => [B::footer(), 'add', B::section('x')];
		yield 'dl in dd' => [B::dd(), 'add', B::dl()];
		yield 'tr in cell' => [B::td(), 'add', B::tr()];
	}

	#[DataProvider('provideWrongChildren')]
	public function testWrongChildrenAreTypeErrors(object $builder, string $method, mixed $child): void {
		$this->expectException(TypeError::class);
		$builder->{$method}($child);
	}

	// ------------------------------------------------------------------

	private static function inline(InlineBuilder $content): string {
		return rtrim(self::aoxml(B::document($content)), "\n");
	}

	private static function aoxml(Document $document): string {
		return (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
	}
}
