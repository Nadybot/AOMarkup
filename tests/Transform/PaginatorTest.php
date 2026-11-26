<?php declare(strict_types=1);

namespace AO\Markup\Tests\Transform;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Export\PlainExporter;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Transform\Paginator;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pagination rules, with plain text and tiny pages so the expectations stay readable
 *
 * The real thing (AOML, 7 500 bytes) is sample 07-history-pages.
 */
final class PaginatorTest extends TestCase {
	private const WINDOW = '<popup window="w">W</popup><window id="w" title="T">';

	/** @return iterable<string,array{int,string,list<string>}> */
	public static function provideCases(): iterable {
		yield 'a section that does not fit starts a new page' => [
			48,
			'<section title="A">aaaa<br>aaaa</section><section title="B">bbbb<br>bbbb<br>bbbb</section></window>',
			["T 1/2\n\nA\n    aaaa\n    aaaa\n", "T 2/2\n\nB\n    bbbb\n    bbbb\n    bbbb\n"],
		];
		yield 'a split section continues without its title' => [
			40,
			'<section title="A">a1<br>a2<br>a3<br>a4<br>a5<br>a6<br>a7</section></window>',
			["T 1/2\n\nA\n    a1\n    a2\n    a3\n    a4\n", "T 2/2\n\n    a5\n    a6\n    a7\n"],
		];
		yield 'pages end only at page breaks' => [
			19,
			'p1<br>p1<pagebreak/>p2<pagebreak/>p3<br>p3</window>',
			["T 1/2\n\np1\np1\n\np2\n", "T 2/2\n\np3\np3\n"],
		];
		yield 'content between page breaks that is too large is split' => [
			18,
			'p1<pagebreak/>l1<br>l2<br>l3<br>l4<br>l5</window>',
			["T 1/2\n\np1\n\nl1\nl2\n", "T 2/2\n\nl3\nl4\nl5\n"],
		];
		yield 'repeat on every page, footer on the last, lists split between items' => [
			25,
			'<repeat>R</repeat><ul><li>i1</li><li>i2</li><li>i3</li><li>i4</li></ul><footer>F</footer></window>',
			["T 1/3\n\nR\n\n• i1\n• i2\n", "T 2/3\n\nR\n\n• i3\n• i4\n", "T 3/3\n\nR\n\nF\n"],
		];
		yield 'split field lists stay aligned' => [
			30,
			'<dl><dt>A</dt><dd>1</dd><dt>B</dt><dd>2</dd><dt>Longer</dt><dd>300</dd></dl></window>',
			["T 1/3\n\nA:        1\n", "T 2/3\n\nB:        2\n", "T 3/3\n\nLonger: 300\n"],
		];
		yield 'split tables repeat the header and keep their column widths' => [
			60,
			'<table border><tr><th>H</th><th align="right">N</th></tr><tr><td>a</td><td>1</td></tr>'
			. '<tr><td>b</td><td>22</td></tr><tr><td>c</td><td>333</td></tr></table></window>',
			["T 1/2\n\nH │   N\n──┼────\na │   1\nb │  22\n", "T 2/2\n\nH │   N\n──┼────\nc │ 333\n"],
		];
	}

	/** @param list<string> $expected */
	#[DataProvider('provideCases')]
	public function testPages(int $maxLength, string $window, array $expected): void {
		$this->assertSame($expected, self::pages(self::WINDOW . $window, $maxLength));
	}

	public function testEveryPageIsACompleteMessage(): void {
		$this->assertSame(
			[
				"See this 1/3!\n\nT 1/3\n\na\nb\nc\nd\n",
				"See this 2/3!\n\nT 2/3\n\ne\nf\ng\nh\n",
				"See this 3/3!\n\nT 3/3\n\ni\n",
			],
			self::pages('See <popup window="w">this</popup>!<window id="w" title="T">a<br>b<br>c<br>d<br>e<br>f<br>g<br>h<br>i</window>', 30)
		);
	}

	public function testADocumentThatFitsIsReturnedAsItIs(): void {
		$document = self::parse(self::WINDOW . 'short</window>');
		$this->assertSame([$document], (new Paginator())->paginate($document, new PlainExporter(), self::context(), 100));
	}

	public function testTheDefaultPageFormat(): void {
		$pages = self::pages(self::WINDOW . implode('<br>', range('a', 't')) . '</window>', 35, null);
		$this->assertStringStartsWith("T (Page 1 / 3)\n", $pages[0]);
	}

	public function testBlocksThatCannotBeSplitAreLoggedOnce(): void {
		$logger = TestLogger::create();
		$pages = self::pages(
			self::WINDOW . '<table border><tr><th>Header</th></tr><tr><td>a row</td></tr></table></window>',
			30,
			logger: $logger
		);
		$this->assertCount(1, $pages);
		$this->assertCount(1, $logger->records->all());
	}

	/** @return list<string> */
	private static function pages(string $input, int $maxLength, ?string $format='%s %d/%d', ?TestLogger $logger=null): array {
		$paginator = isset($format) ? new Paginator($format) : new Paginator();
		$exporter = new PlainExporter();
		$context = self::context($logger);
		return array_map(
			static fn (Document $page): string => $exporter->export($page, $context),
			$paginator->paginate(self::parse($input), $exporter, $context, $maxLength)
		);
	}

	private static function parse(string $input): Document {
		return (new AoXmlParser(WidgetRegistry::withBuiltins(), TestLogger::create()))->parse($input);
	}

	private static function context(?TestLogger $logger=null): RenderContext {
		return new RenderContext(new ArrayPlaceholderProvider([]), logger: $logger ?? TestLogger::create());
	}
}
