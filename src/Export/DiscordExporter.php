<?php declare(strict_types=1);

namespace AO\Markup\Export;

use function Safe\json_encode;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Discord\{ChoiceRenderer, DiscordRenderer, Limits, SliderRenderer};
use AO\Markup\Metric\{MonospaceMetric, TextMetric};
use AO\Markup\Transform\{Paginator, PlaceholderResolver, WidgetExpander};
use AO\Markup\Widget\Widget;

/**
 * JSON of the Discord messages for a document: a list of messages with content, embeds, components
 *
 * Every window is an embed. Messages beyond Discord's limits are split like a paginated
 * window (Limits); field values that are too long continue in further fields.
 * <slider> becomes a code block, <choice> a select menu; more native renderers can be
 * added with addNativeRenderer().
 */
final class DiscordExporter extends BaseExporter {
	public function __construct(
		private Paginator $paginator=new Paginator(),
	) {
		$this->addNativeRenderer(new SliderRenderer());
		$this->addNativeRenderer(new ChoiceRenderer());
	}

	public function export(Document $document, RenderContext $context): string {
		return json_encode(
			$this->toMessages($document, $context),
			\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
		);
	}

	/**
	 * The messages as arrays, ready for json_encode() or a Discord library
	 *
	 * @return list<array<string,mixed>>
	 */
	public function toMessages(Document $document, RenderContext $context): array {
		$pages = $this->paginator->paginateWith(
			$document,
			$context,
			fn (Document $page, RenderContext $quiet): int => strlen(json_encode($this->message($page, $quiet))),
			fn (Document $page, RenderContext $quiet): bool => Limits::fits($this->message($page, $quiet)),
		);
		return array_map(fn (Document $page): array => $this->message($page, $context), $pages);
	}

	public function getMetric(): TextMetric {
		return new MonospaceMetric();
	}

	public function getFormat(): string {
		return 'discord';
	}

	/** @return array<string,mixed> */
	private function message(Document $document, RenderContext $context): array {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->getMetric(), $this->getNativeWidgets()))->transform($document, $context);
		return (new DiscordRenderer($context, fn (Widget $widget): ?NativeRenderer => $this->getNativeRenderer($widget)))
			->render($document);
	}
}
