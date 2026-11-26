<?php declare(strict_types=1);

namespace AO\Markup\Tests\Widget;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Metric\{AoFontMetric, MonospaceMetric};
use AO\Markup\Node\{CommandChannel, CommandLink, LineBreak, Node, Paragraph, Style, Text};
use AO\Markup\Widget\Builtin\{Choice, Option, Param, Slider, Unavailable};
use AO\Markup\Widget\{ExpansionContext, WidgetNode, WidgetRegistry};
use PHPUnit\Framework\TestCase;

final class WidgetTest extends TestCase {
	public function testRegistryKnowsBuiltins(): void {
		$registry = WidgetRegistry::withBuiltins();
		$this->assertSame(Slider::class, $registry->get('slider'));
		$this->assertSame(Unavailable::class, $registry->get('unavailable'));
		$this->assertSame(Param::class, $registry->get('param'));
		$this->assertFalse($registry->has('table'));
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
			'1170 DEF ' . str_repeat('l', 36) . '│' . str_repeat('l', 13) . " AGG 135\n"
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
