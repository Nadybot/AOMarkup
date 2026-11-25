<?php declare(strict_types=1);

namespace AO\Markup\Widget\Builtin;

use AO\Markup\Node\{LineBreak, Node, Paragraph, Style, StyleName, Text};
use AO\Markup\Widget\{AsWidget, ExpansionContext, Widget};

/**
 * A horizontal scale with a marker, e.g. the agg/def slider
 *
 * Fallback:
 *   <left> <green>|||||</green><red>│</red><green>|||</green> <right>
 *                       <marker>
 */
#[AsWidget('slider')]
final class Slider implements Widget {
	/** Number of bar characters */
	public const LENGTH = 50;

	public function __construct(
		public readonly float $pos,
		public readonly float $min=0,
		public readonly float $max=100,
		public readonly ?string $left=null,
		public readonly ?string $right=null,
		public readonly ?string $marker=null,
	) {
	}

	public function fallback(ExpansionContext $ctx, Node ...$children): Node {
		$range = $this->max - $this->min;
		$ratio = $range > 0 ? ($this->pos - $this->min) / $range : 0;
		$markerPos = (int)round(max(0.0, min(1.0, $ratio)) * (self::LENGTH - 1));

		$prefix = isset($this->left) ? "{$this->left} " : '';
		$leftBar = str_repeat('|', $markerPos);
		$rightBar = str_repeat('|', self::LENGTH - 1 - $markerPos);

		$bar = [];
		if ($prefix !== '') {
			$bar []= new Text($prefix);
		}
		if ($leftBar !== '') {
			$bar []= new Style(StyleName::Green, new Text($leftBar));
		}
		$bar []= new Style(StyleName::Red, new Text('│'));
		if ($rightBar !== '') {
			$bar []= new Style(StyleName::Green, new Text($rightBar));
		}
		if (isset($this->right)) {
			$bar []= new Text(" {$this->right}");
		}
		if (!isset($this->marker)) {
			return new Paragraph(...$bar);
		}

		// Center the marker text below the marker
		$metric = $ctx->metric;
		$markerX = $metric->width($prefix . $leftBar) + intdiv($metric->width('│'), 2);
		$textX = max(0, $markerX - intdiv($metric->width($this->marker), 2));
		$spaces = $metric->spacesBetween(0, $textX);

		return new Paragraph(
			...[
				...$bar,
				new LineBreak(),
				new Text(str_repeat(' ', $spaces) . $this->marker),
			]
		);
	}
}
