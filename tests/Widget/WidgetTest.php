<?php declare(strict_types=1);

namespace AO\Markup\Tests\Widget;

use AO\Markup\Context\{ArrayPlaceholderProvider, DefaultTimeFormatter, RenderContext, TimeFormatter};
use AO\Markup\Metric\{AoFontMetric, MonospaceMetric};
use AO\Markup\Node\{CommandChannel, CommandLink, LineBreak, Node, Paragraph, Style, Text};
use AO\Markup\Widget\Builtin\{Choice, Date, Datetime, Duration, Option, Param, Slider, Time, Unavailable};
use AO\Markup\Widget\{ExpansionContext, WidgetNode, WidgetRegistry};
use Beste\Psr\Log\TestLogger;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Safe\DateTimeImmutable;

final class WidgetTest extends TestCase {
	public function testRegistryKnowsBuiltins(): void {
		$registry = WidgetRegistry::withBuiltins();
		$this->assertSame(Slider::class, $registry->get('slider'));
		$this->assertSame(Unavailable::class, $registry->get('unavailable'));
		$this->assertSame(Param::class, $registry->get('param'));
		$this->assertSame(Datetime::class, $registry->get('datetime'));
		$this->assertSame(Date::class, $registry->get('date'));
		$this->assertSame(Time::class, $registry->get('time'));
		$this->assertSame(Duration::class, $registry->get('duration'));
		$this->assertFalse($registry->has('table'));
	}

	public function testTimeFallbacksUseTheFormatter(): void {
		$formatter = new DefaultTimeFormatter(new DateTimeZone('Europe/Berlin'), 'd.m.Y H:i', 'd.m.Y', 'H:i');
		$ctx = new ExpansionContext(new RenderContext(new ArrayPlaceholderProvider([]), time: $formatter), new MonospaceMetric());
		$value = new DateTimeImmutable('2026-11-26T23:02:00Z');
		$this->assertSame('27.11.2026 00:02', self::plain((new Datetime($value))->fallback($ctx)));
		$this->assertSame('27.11.2026', self::plain((new Date($value))->fallback($ctx)));
		$this->assertSame('00:02', self::plain((new Time($value))->fallback($ctx)));
		$this->assertSame('6 days 3 hrs', self::plain((new Duration(529_200))->fallback($ctx)));
	}

	public function testTimeFallbacksUseACustomFormatter(): void {
		$formatter = new class () implements TimeFormatter {
			public function dateTime(DateTimeInterface $value): string {
				return 'dt ' . $value->format('U');
			}

			public function date(DateTimeInterface $value): string {
				return 'd ' . $value->format('U');
			}

			public function time(DateTimeInterface $value): string {
				return 't ' . $value->format('U');
			}

			public function duration(int $seconds): string {
				return "{$seconds}s";
			}
		};
		$ctx = new ExpansionContext(new RenderContext(new ArrayPlaceholderProvider([]), time: $formatter), new MonospaceMetric());
		$value = new DateTimeImmutable('@1795712520');
		$this->assertSame('dt 1795712520', self::plain((new Datetime($value))->fallback($ctx)));
		$this->assertSame('d 1795712520', self::plain((new Date($value))->fallback($ctx)));
		$this->assertSame('t 1795712520', self::plain((new Time($value))->fallback($ctx)));
		$this->assertSame('90s', self::plain((new Duration(90))->fallback($ctx)));
	}

	public function testRegistryParsesTimes(): void {
		$registry = WidgetRegistry::withBuiltins();
		$logger = TestLogger::create();
		$node = $registry->create('datetime', ['value' => '2026-11-26T18:02:00+01:00'], $logger);
		$this->assertNotNull($node);
		$this->assertInstanceOf(Datetime::class, $node->widget);
		$this->assertSame(1_795_712_520, $node->widget->value->getTimestamp());
		$this->assertSame('+01:00', $node->widget->value->format('P'));

		$this->assertNull($registry->create('time', ['value' => '17:02'], $logger));
		$this->assertNull($registry->create('duration', ['seconds' => 'soon'], $logger));
		$this->assertSame(
			[
				'<time>: invalid value "17:02" for attribute "value"',
				'<duration>: invalid value "soon" for attribute "seconds"',
			],
			array_map(static fn ($r): string => (string)$r->message, $logger->records->all())
		);
	}

	public function testSliderFallback(): void {
		$ctx = new ExpansionContext(new RenderContext(new ArrayPlaceholderProvider([])), new MonospaceMetric());
		$slider = new Slider(pos: 73, left: '1170 DEF', right: 'AGG 135', marker: 'You: 300');
		$this->assertSame(
			'1170 DEF ' . str_repeat('|', 36) . '│' . str_repeat('|', 13) . " AGG 135\n"
			. str_repeat(' ', 41) . 'You: 300',
			self::plain($slider->fallback($ctx))
		);
	}

	public function testSliderUsesNarrowBarInAoFont(): void {
		$ctx = new ExpansionContext(new RenderContext(new ArrayPlaceholderProvider([])), new AoFontMetric());
		$slider = new Slider(pos: 73, left: '1170 DEF', right: 'AGG 135', marker: 'You: 300');
		$this->assertSame(
			'1170 DEF ' . str_repeat('l', 36) . '|' . str_repeat('l', 13) . " AGG 135\n"
			. str_repeat(' ', 37) . 'You: 300',
			self::plain($slider->fallback($ctx))
		);
	}

	public function testChoiceFallback(): void {
		$ctx = new ExpansionContext(new RenderContext(new ArrayPlaceholderProvider([])), new MonospaceMetric());
		$options = [
			new WidgetNode(new Option('0', true), new Text('No')),
			new WidgetNode(new Option('1'), new Text('Always')),
		];
		$choice = new Choice('settings save x', CommandChannel::Priv);
		$this->assertSame("No (current)\nAlways [select]", self::plain($choice->fallback($ctx, ...$options)));

		$node = $choice->fallback($ctx, ...$options);
		$this->assertInstanceOf(Paragraph::class, $node);
		$link = $node->children[5];
		$this->assertInstanceOf(CommandLink::class, $link);
		$this->assertSame('settings save x 1', $link->command);
		$this->assertSame(CommandChannel::Priv, $link->via);

		$inline = new Choice('ofabarmor nt', inline: true);
		$this->assertSame('No Always', self::plain($inline->fallback($ctx, ...$options)));
	}

	private static function plain(Node $node): string {
		return match (true) {
			$node instanceof Text => $node->text,
			$node instanceof LineBreak => "\n",
			$node instanceof Style, $node instanceof Paragraph, $node instanceof CommandLink => implode('', array_map(self::plain(...), $node->children)),
			default => throw new \LogicException($node::class),
		};
	}
}
