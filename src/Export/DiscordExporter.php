<?php declare(strict_types=1);

namespace AO\Markup\Export;

use function Safe\json_encode;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Discord\{ChoiceRenderer, DiscordRenderer, SliderRenderer};
use AO\Markup\Metric\{MonospaceMetric, TextMetric};
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};
use AO\Markup\Widget\Widget;

/**
 * JSON of a Discord message: content, one embed per window, components
 *
 * <slider> becomes a code block, <choice> a select menu; more native renderers
 * can be added with addNativeRenderer().
 */
final class DiscordExporter extends BaseExporter {
	public function __construct() {
		$this->addNativeRenderer(new SliderRenderer());
		$this->addNativeRenderer(new ChoiceRenderer());
	}

	public function export(Document $document, RenderContext $context): string {
		return json_encode(
			$this->toArray($document, $context),
			\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
		);
	}

	/**
	 * The message as an array, ready for json_encode() or a Discord library
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(Document $document, RenderContext $context): array {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->getMetric(), $this->getNativeWidgets()))->transform($document, $context);
		return (new DiscordRenderer($context, fn (Widget $widget): ?NativeRenderer => $this->getNativeRenderer($widget)))
			->render($document);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'discord';
	}
}
