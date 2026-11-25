<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use AO\Markup\Context\RenderContext;
use AO\Markup\Metric\TextMetric;

/** What a widget may know about the target when building its fallback */
final class ExpansionContext {
	public function __construct(
		public readonly RenderContext $render,
		public readonly TextMetric $metric,
	) {
	}
}
