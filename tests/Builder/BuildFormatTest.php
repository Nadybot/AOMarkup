<?php declare(strict_types=1);

namespace AO\Markup\Tests\Builder;

use AO\Markup\Build as B;
use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\AoXmlSerializer;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\TestCase;

/** B::format() and B::aoxml() */
final class BuildFormatTest extends TestCase {
	private TestLogger $logger;

	protected function setUp(): void {
		$this->logger = TestLogger::create();
		B::setLogger($this->logger);
	}

	protected function tearDown(): void {
		B::setLogger(null);
	}

	public function testArgumentsAreNeverParsed(): void {
		$this->assertSame(
			"No one matching <strong>&lt;/strong&gt;&lt;red&gt;x</strong> is banned.\n",
			self::serialize(B::document(B::format('No one matching <strong>{search}</strong> is banned.', search: '</strong><red>x')))
		);
		$this->assertSame([], $this->warnings());
	}

	public function testAnArrayCanBeSpread(): void {
		$params = ['name' => 'Nady', 'count' => 3];
		$this->assertSame("Nady has 3.\n", self::serialize(B::document(B::format('{name} has {count}.', ...$params))));
	}

	public function testArgumentsAreContent(): void {
		$this->assertSame(
			"<char>Nady</char> bought <item id=\"1234\" ql=\"200\">Pistol</item> for <strong>1,000</strong> credits.\n",
			self::serialize(B::document(B::format(
				'{char} bought {item} for <strong>{price}</strong> credits.',
				char: B::char('Nady'),
				item: B::item(low: 1234, ql: 200, label: 'Pistol'),
				price: number_format(1000),
			)))
		);
	}

	public function testSlotsInStylesAndLinks(): void {
		$this->assertSame(
			"<cmd command=\"ban list\"><strong>2 bans</strong></cmd>\n",
			self::serialize(B::document(B::format('<cmd command="ban list"><strong>{n}</strong></cmd>', n: B::count(2, 'ban'))))
		);
	}

	public function testNullIsNothing(): void {
		$this->assertSame("ab\n", self::serialize(B::document(B::format('a{x}b', x: null))));
		$this->assertSame([], $this->warnings());
	}

	public function testDoubleBraceIsABrace(): void {
		$this->assertSame("{x} {y\n", self::serialize(B::document(B::format('{{x} {y'))));
		$this->assertSame("{17}\n", self::serialize(B::document(B::format('{{{x}}', x: 17))));
		$this->assertSame([], $this->warnings());
	}

	public function testPopupLinkAsArgument(): void {
		$bans = B::window('Bans')->add('x');
		$this->assertSame(
			"There are <popup window=\"window1\"><strong>3 bans</strong></popup>.\n\n<window id=\"window1\" title=\"Bans\">\n\tx\n</window>\n",
			self::serialize(B::document(B::format('There are {bans}.', bans: $bans->link(B::strong(B::count(3, 'ban'))))))
		);
	}

	public function testFormatIsInlineContent(): void {
		$this->assertSame(
			"<dl>\n\t<dt>By</dt><dd><char>Nady</char> (<strong>admin</strong>)</dd>\n</dl>\n",
			self::serialize(B::document(B::dl()->addField('By', B::format('{who} (<strong>{rank}</strong>)', who: B::char('Nady'), rank: 'admin'))))
		);
	}

	public function testMissingAndUnusedArguments(): void {
		$this->assertSame("a  b\n", self::serialize(B::document(B::format('a {x} b', 5, y: 1))));
		$this->assertSame([
			'B::format(): positional argument #0 ignored, slots are filled by name',
			'B::format(): argument "y" ignored, there is no slot {y}',
			'B::format(): no argument for slot {x}',
		], $this->warnings());
	}

	public function testSlotsInAttributesAreNotFilled(): void {
		$this->assertSame(
			"<cmd command=\"ban {name}\">ban</cmd>\n",
			self::serialize(B::document(B::format('<cmd command="ban {name}">ban</cmd>')))
		);
		$this->assertSame([
			'B::format(): AoXML line 1: {name} in attribute command of <cmd> is not filled, slots are only allowed in content; pass the element as an argument instead',
		], $this->warnings());
	}

	public function testPopupsAreNotAllowed(): void {
		$this->assertSame("see p\n", self::serialize(B::document(B::format('see <popup window="a">p</popup>'))));
		$this->assertContains('B::format(): <popup> is not allowed here, pass $window->link() as an argument', $this->warnings());
	}

	public function testBlocksInFormatAreDropped(): void {
		$this->assertSame("a\n<br/>b\n", self::serialize(B::document(B::format("a\n\nb<hr/>"))));
		$this->assertSame(
			['B::format(): blocks are not allowed in B::format(), use B::aoxml(); HorizontalRule dropped'],
			$this->warnings()
		);
	}

	public function testWarningsOfACachedFormatAreRepeated(): void {
		B::format('a <b>{x}</b> cached', x: 1);
		$first = $this->warnings();
		$this->assertNotSame([], $first);
		B::format('a <b>{x}</b> cached', x: 1);
		$this->assertSame([...$first, ...$first], $this->warnings());
	}

	public function testAoxmlGivesBlocks(): void {
		$this->assertSame(
			"<section title=\"Bans\">\n\tBy <char>Nady</char>\n\n\t<table>\n\t\t<tr><td>a</td></tr>\n\t</table>\n</section>\n\n<hr/>\n",
			self::serialize(B::document(B::aoxml(
				"<section title=\"Bans\">By {who}\n\n{table}</section>\n<hr/>",
				who: B::char('Nady'),
				table: B::table()->addRow('a'),
			)))
		);
		$this->assertSame([], $this->warnings());
	}

	public function testAoxmlBlocksAreAddedInPlace(): void {
		$this->assertSame(
			"<section title=\"T\">\n\tx\n\n\t<hr/>\n\n\ty\n</section>\n",
			self::serialize(B::document(B::section('T')->add('x', B::aoxml('<hr/>'), 'y')))
		);
	}

	public function testABlockSplitsItsParagraph(): void {
		$this->assertSame(
			"x\n\n<table>\n\t<tr><td>1</td></tr>\n</table>\n\ny\n",
			self::serialize(B::document(B::aoxml('x {t} y', t: B::table()->addRow(1))))
		);
	}

	public function testABlockCannotGoIntoALine(): void {
		$this->assertSame(
			"a <strong></strong>\n",
			self::serialize(B::document(B::aoxml('a <strong>{t}</strong>', t: B::table()->addRow(1))))
		);
		$this->assertSame(['B::aoxml(): argument "t" is a block, dropped where {t} is inside a line'], $this->warnings());
	}

	public function testWindowsAreNotAllowed(): void {
		$document = B::document(B::aoxml('<window id="w" title="W">x</window>'));
		$this->assertSame([], $document->windows);
		$this->assertContains('B::aoxml(): <window> is not allowed here, build windows with B::window()', $this->warnings());
	}

	/** @return list<string> */
	private function warnings(): array {
		return array_map(static fn ($r): string => (string)$r->message, $this->logger->records->all());
	}

	private static function serialize(Document $document): string {
		return (new AoXmlSerializer())->export($document, new RenderContext(new ArrayPlaceholderProvider([])));
	}
}
