<?php declare(strict_types=1);

namespace AO\Markup\Tests;

use function Safe\{file_get_contents, glob, preg_replace};

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Document;
use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Export\{AnsiExporter, AomlExporter, BbmlExporter, DiscordExporter, Exporter, HtmlExporter, LePanelExporter, PlainExporter};
use AO\Markup\Parser\AoXmlParser;
use AO\Markup\Tests\Support\AnsiNotation;
use AO\Markup\Transform\Paginator;
use AO\Markup\Widget\WidgetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Golden tests: every sample's input.aoxml must render exactly as its expected.* files */
final class SamplesTest extends TestCase {
	private const SAMPLE_DIR = __DIR__ . '/Samples';

	/** Page size of the formats that are paginated, like Nadybot's default max_blob_size */
	public const PAGE_SIZE = 7_500;

	/** Formats that are split into pages */
	public const PAGINATED = ['aoml', 'bbml'];

	/** Line between two pages (messages) in an expected file */
	public const PAGE_SEPARATOR = "\n----- next message -----\n";

	/** @return iterable<string,array{string,string}> */
	public static function provideSamples(): iterable {
		$samples = glob(self::SAMPLE_DIR . '/*', GLOB_ONLYDIR);
		foreach ($samples as $dir) {
			foreach (array_keys(self::exporters()) as $extension) {
				yield basename($dir) . " → {$extension}" => [basename($dir), $extension];
			}
		}
	}

	/** @return array<string,Exporter> */
	public static function exporters(): array {
		return [
			'aoml' => new AomlExporter(),
			'bbml' => new BbmlExporter(),
			'lepanel' => new LePanelExporter(),
			'ansi' => new AnsiExporter(),
			'txt' => new PlainExporter(),
			'html' => new HtmlExporter(),
			'discord.json' => new DiscordExporter(),
		];
	}

	public static function context(): RenderContext {
		return new RenderContext(new ArrayPlaceholderProvider([
			'myname' => 'Nadybot',
			'symbol' => '!',
			'myguild' => 'Troet',
		]));
	}

	#[DataProvider('provideSamples')]
	public function testSample(string $sample, string $extension): void {
		$dir = self::SAMPLE_DIR . "/{$sample}";
		$input = file_get_contents("{$dir}/input.aoxml");
		$expected = file_get_contents("{$dir}/expected.{$extension}");
		if ($extension === 'ansi') {
			$expected = AnsiNotation::toEscapes($expected);
		}

		$exporter = self::exporters()[$extension];
		try {
			$document = (new AoXmlParser(WidgetRegistry::withBuiltins()))->parse($input);
			$actual = self::render($document, $exporter, $extension);
		} catch (NotImplementedException $e) {
			$this->markTestIncomplete($e->getMessage());
		}
		if ($extension === 'discord.json') {
			$this->assertJsonStringEqualsJsonString($expected, $actual);
			return;
		}
		$this->assertSame(rtrim($expected, "\n"), rtrim($actual, "\n"));
	}

	/** Render a document; paginated formats give all pages, separated by PAGE_SEPARATOR */
	public static function render(Document $document, Exporter $exporter, string $extension): string {
		if (!in_array($extension, self::PAGINATED, true)) {
			return $exporter->export($document, self::context());
		}
		$pages = (new Paginator())->paginate($document, $exporter, self::context(), self::PAGE_SIZE);
		return implode(self::PAGE_SEPARATOR, array_map(static fn (Document $page): string => $exporter->export($page, self::context()), $pages));
	}

	/** The expected files themselves must be well-formed */
	public function testExpectedFilesAreValid(): void {
		$samples = glob(self::SAMPLE_DIR . '/*', GLOB_ONLYDIR);
		$this->assertNotEmpty($samples);
		foreach ($samples as $dir) {
			foreach (['legacy.bbml', 'input.aoxml', ...array_map(static fn (string $e): string => "expected.{$e}", array_keys(self::exporters()))] as $file) {
				$this->assertFileExists("{$dir}/{$file}");
			}
			$this->assertJson(file_get_contents("{$dir}/expected.discord.json"));
			$ansi = file_get_contents("{$dir}/expected.ansi");
			AnsiNotation::toEscapes($ansi);

			// Plain text and ANSI must show the same text
			$plain = file_get_contents("{$dir}/expected.txt");
			$stripped = preg_replace(["/\e\\]8;;[^\e]*\e\\\\/", "/\e\\[[0-9;]*m/"], '', AnsiNotation::toEscapes($ansi));
			$this->assertSame($plain, $stripped, basename($dir) . ': ANSI and plain text differ');
		}
	}
}
