<?php declare(strict_types=1);

namespace AO\Markup\Tests\Node;

use function Safe\glob;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\{Document, Window};
use AO\Markup\Export\{AnsiExporter, AomlExporter, BbmlExporter, DiscordExporter, Exporter, HtmlExporter, PlainExporter};
use AO\Markup\Node\{
	Align,
	BlockNode,
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandLink,
	Field,
	FieldList,
	FieldValue,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	ImageSource,
	Indent,
	ItemLink,
	Keep,
	LineBreak,
	ListBlock,
	ListItem,
	NanoLink,
	Node,
	PageBreak,
	Paragraph,
	Placeholder,
	PopupLink,
	Repeat,
	Section,
	SkillLink,
	Style,
	StyleName,
	Table,
	TableCell,
	TableRow,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Transform\Tree;
use Beste\Psr\Log\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Every node class is handled everywhere
 *
 * Renderers and Tree tell nodes apart with instanceof; a node class they forget only
 * ends up in a default branch. This test fails for a new node class until it has an
 * example here, and then for every exporter that cannot render it.
 */
final class CoverageTest extends TestCase {
	/**
	 * One example of every node class; structural nodes (fields, rows, cells, list
	 * items) are rendered as part of their parent
	 *
	 * @return array<class-string<Node>,Node>
	 */
	public static function examples(): array {
		$text = static fn (): Text => new Text('x');
		return [
			Text::class => $text(),
			LineBreak::class => new LineBreak(),
			Fragment::class => new Fragment($text()),
			Paragraph::class => new Paragraph($text()),
			Style::class => new Style(StyleName::Strong, $text()),
			Color::class => new Color('#123456', $text()),
			ItemLink::class => new ItemLink(1, 2, 3, $text()),
			NanoLink::class => new NanoLink(1, $text()),
			SkillLink::class => new SkillLink(1, $text()),
			CharLink::class => new CharLink('Nady', $text()),
			CommandLink::class => new CommandLink('help', children: $text()),
			ClientCommandLink::class => new ClientCommandLink('/assist Nady', $text()),
			WaypointLink::class => new WaypointLink(1, 2, 3, $text()),
			UrlLink::class => new UrlLink('https://x.org', $text()),
			PopupLink::class => new PopupLink('other', $text()),
			Image::class => new Image(ImageSource::Rdb, '1'),
			Placeholder::class => new Placeholder('myname'),
			Section::class => new Section('S', new Paragraph($text())),
			Heading::class => new Heading(1, $text()),
			Indent::class => new Indent(new Paragraph($text())),
			Center::class => new Center(new Paragraph($text())),
			Keep::class => new Keep(new Paragraph($text())),
			Repeat::class => new Repeat(new Paragraph($text())),
			Footer::class => new Footer(new Paragraph($text())),
			HorizontalRule::class => new HorizontalRule(),
			PageBreak::class => new PageBreak(),
			FieldList::class => new FieldList(new Field([$text()], new FieldValue('u', $text()))),
			Field::class => new FieldList(new Field([$text()], new FieldValue(null, $text()))),
			FieldValue::class => new FieldList(new Field([$text()], new FieldValue(null, $text()))),
			ListBlock::class => new ListBlock(false, 1, new ListItem(null, new Paragraph($text()))),
			ListItem::class => new ListBlock(true, 1, new ListItem(new Image(ImageSource::Tdb, 'GFX'), new Paragraph($text()))),
			Table::class => new Table(true, new TableRow(new TableCell(true, Align::Right, 1, new Paragraph($text())))),
			TableRow::class => new Table(false, new TableRow(new TableCell(false, null, 2, new Paragraph($text())))),
			TableCell::class => new Table(false, new TableRow(new TableCell(false, Align::Center, 1, new Paragraph($text())))),
		];
	}

	/** Every class in src/Node that is a node has an example */
	public function testEveryNodeClassHasAnExample(): void {
		$missing = [];
		foreach (glob(__DIR__ . '/../../src/Node/*.php') as $file) {
			$class = 'AO\\Markup\\Node\\' . basename($file, '.php');
			if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
				continue;
			}
			$reflection = new ReflectionClass($class);
			if ($reflection->isInterface() || $reflection->isAbstract() || $reflection->isEnum() || !$reflection->implementsInterface(Node::class)) {
				continue;
			}
			if (!isset(self::examples()[$class])) {
				$missing []= $class;
			}
		}
		$this->assertSame([], $missing, 'Add an example to CoverageTest::examples()');
	}

	/** @return iterable<string,array{string,Exporter}> */
	public static function provideExporters(): iterable {
		$exporters = [
			'plain' => new PlainExporter(),
			'ansi' => new AnsiExporter(),
			'aoml' => new AomlExporter(),
			'bbml' => new BbmlExporter(),
			'html' => new HtmlExporter(),
			'discord' => new DiscordExporter(),
		];
		foreach ($exporters as $name => $exporter) {
			yield $name => [$name, $exporter];
		}
	}

	/** Every node renders without "Cannot render", in a window and in the message */
	#[DataProvider('provideExporters')]
	public function testEveryExporterRendersEveryNode(string $name, Exporter $exporter): void {
		$problems = [];
		foreach (self::examples() as $class => $node) {
			$block = $node instanceof BlockNode ? $node : new Paragraph($node);
			$document = new Document(
				new Fragment(new Paragraph(new Text('See '), new PopupLink('w', new Text('this'))), $block),
				['w' => new Window('w', 'T', $block), 'other' => new Window('other', 'O', new Paragraph(new Text('o')))]
			);
			$logger = TestLogger::create();
			$exporter->export($document, new RenderContext(new ArrayPlaceholderProvider(['myname' => 'Bot', 'symbol' => '!']), logger: $logger));
			foreach ($logger->records->all() as $record) {
				$problems []= "{$class}: {$record->message}";
			}
		}
		$this->assertSame([], $problems, "{$name} does not handle every node");
	}

	/** Tree descends into every node that has content: mapping Text reaches it */
	public function testTreeReachesTheContentOfEveryNode(): void {
		$unreached = [];
		foreach (self::examples() as $class => $node) {
			$found = false;
			$map = static function (Node $n) use (&$map, &$found): Node {
				if ($n instanceof Text) {
					$found = true;
					return new Text('y');
				}
				return Tree::mapChildren($n, $map);
			};
			$mapped = Tree::mapChildren($node, $map);
			$hasText = !in_array($class, [Text::class, LineBreak::class, Image::class, Placeholder::class, HorizontalRule::class, PageBreak::class], true);
			if ($hasText && (!$found || $mapped === $node)) {
				$unreached []= $class;
			}
		}
		$this->assertSame([], $unreached);
	}
}
