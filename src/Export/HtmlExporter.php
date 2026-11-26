<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Html\{ChoiceRenderer, HtmlRenderer, SliderRenderer};
use AO\Markup\Metric\{MonospaceMetric, TextMetric};
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};
use AO\Markup\Widget\Widget;

/**
 * Semantic HTML with aom-* CSS classes
 *
 * <slider> and <choice> are rendered natively (<meter>, <select>); more native
 * renderers can be added with addNativeRenderer().
 */
final class HtmlExporter extends BaseExporter {
	public function __construct() {
		$this->addNativeRenderer(new SliderRenderer());
		$this->addNativeRenderer(new ChoiceRenderer());
	}

	public function export(Document $document, RenderContext $context): string {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->getMetric(), $this->getNativeWidgets()))->transform($document, $context);
		return (new HtmlRenderer($context, fn (Widget $widget): ?NativeRenderer => $this->getNativeRenderer($widget)))
			->render($document);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'html';
	}
}
