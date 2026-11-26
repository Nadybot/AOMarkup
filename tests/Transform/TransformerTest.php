<?php declare(strict_types=1);

namespace AO\Markup\Tests\Transform;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Metric\MonospaceMetric;
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\TreeDump;
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};
use AO\Markup\Widget\Builtin\Choice;
use AO\Markup\Widget\WidgetRegistry;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\TestCase;

final class TransformerTest extends TestCase {
	public function testPlaceholdersAreResolvedEverywhere(): void {
		$logger = TestLogger::create();
		$document = (new PlaceholderResolver())->transform(
			self::parse('<symbol/>a<window id="w" title="T"><dl><dt><myname/></dt><dd><var name="x"/></dd></dl></window>'),
			self::context($logger)
		);
		$this->assertSame(
			<<<'TREE'
				Paragraph
				  Text "!"
				  Text "a"
				Window w "T"
				  FieldList
				    Field
				      .name
				        Text "Bot"
				      FieldValue
				        Text ""
				TREE,
			TreeDump::document($document)
		);
		$this->assertSame(['No value for the placeholder "x"'], array_map(
			static fn ($r): string => (string)$r->message,
			$logger->records->all()
		));
	}

	public function testWidgetsAreExpandedRecursively(): void {
		$document = (new WidgetExpander(new MonospaceMetric()))->transform(
			self::parse('<table><tr><td><syntax>a <param>b</param></syntax> <unavailable/></td></tr></table>'),
			self::context()
		);
		$this->assertStringNotContainsString('WidgetNode', TreeDump::document($document));
		$this->assertStringContainsString('"<"', TreeDump::document($document));
	}

	public function testChoiceGetsItsOptionsUnexpanded(): void {
		$document = (new WidgetExpander(new MonospaceMetric()))->transform(
			self::parse('<choice command="x"><option value="1">One <unavailable/></option></choice>'),
			self::context()
		);
		$dump = TreeDump::document($document);
		$this->assertStringContainsString('"x 1"', $dump);
		$this->assertStringContainsString('"(!)"', $dump);
		$this->assertStringNotContainsString('WidgetNode', $dump);
	}

	public function testNativeWidgetsAreKept(): void {
		$input = self::parse('<choice command="x"><option value="1">One</option></choice>');
		$document = (new WidgetExpander(new MonospaceMetric(), [Choice::class]))->transform($input, self::context());
		$this->assertSame(TreeDump::document($input), TreeDump::document($document));
	}

	private static function parse(string $input): Document {
		return (new AoXmlParser(WidgetRegistry::withBuiltins(), TestLogger::create()))->parse($input);
	}

	private static function context(?TestLogger $logger=null): RenderContext {
		return new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger ?? TestLogger::create());
	}
}
