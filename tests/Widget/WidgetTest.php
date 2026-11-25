<?php declare(strict_types=1);

namespace AO\Markup\Tests\Widget;

use AO\Markup\Context\{ArrayPlaceholderProvider, RenderContext};
use AO\Markup\Metric\MonospaceMetric;
use AO\Markup\Node\{LineBreak, Node, Paragraph, Style, Text};
use AO\Markup\Widget\Builtin\{Param, Slider, Unavailable};
use AO\Markup\Widget\{ExpansionContext, WidgetRegistry};
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

	private static function plain(Node $node): string {
		return match (true) {
			$node instanceof Text => $node->text,
			$node instanceof LineBreak => "\n",
			$node instanceof Style, $node instanceof Paragraph => implode('', array_map(self::plain(...), $node->children)),
			default => throw new \LogicException($node::class),
		};
	}
}
