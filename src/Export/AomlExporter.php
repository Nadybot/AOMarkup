<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Game\{AomlStyler, GameRenderer};
use AO\Markup\Metric\{AoFontMetric, TextMetric};
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};

/** AOML for the official Anarchy Online client; tables and fields are aligned with spaces */
final class AomlExporter extends BaseExporter {
	/**
	 * @param string     $bullet Prefix of unordered list items, followed by a space
	 * @param TextMetric $metric Pixel widths of the client font
	 */
	public function __construct(
		public readonly string $bullet='-',
		private TextMetric $metric=new AoFontMetric(),
	) {
	}

	public function export(Document $document, RenderContext $context): string {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->metric))->transform($document, $context);
		return (new GameRenderer($context, $this->metric, new AomlStyler($context->theme), $this->bullet))->render($document);
	}

	public function getMetric(): TextMetric {
		return $this->metric;
	}

	public function getFormat(): string {
		return 'aoml';
	}
}
