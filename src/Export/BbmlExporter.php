<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Document;
use AO\Markup\Export\Game\{BbmlStyler, GameRenderer};
use AO\Markup\Metric\{AoFontMetric, TextMetric};
use AO\Markup\Node\Table;
use AO\Markup\Transform\{PlaceholderResolver, WidgetExpander};
use WeakMap;

/**
 * BBML, the Budabot markup language: AOML that keeps the named styles
 *
 * Like AomlExporter, but <highlight>, <header2>, <green>, … stay named and are closed
 * with <end>, so the receiving bot applies its own theme. The window colour and the
 * placeholders are resolved, like Nadybot's NadyNative relay does. Meant for relays to bots that do
 * not understand AoXML.
 */
final class BbmlExporter extends BaseExporter {
	/** @var WeakMap<Table,array{array<int,int>,array<int,bool>}> Column layout of whole tables, see GameRenderer */
	private WeakMap $columns;

	/**
	 * @param string     $bullet Prefix of unordered list items, followed by a space
	 * @param TextMetric $metric Pixel widths of the client font
	 */
	public function __construct(
		public readonly string $bullet='-',
		private TextMetric $metric=new AoFontMetric(),
	) {
		$this->columns = new WeakMap();
	}

	public function export(Document $document, RenderContext $context): string {
		$document = (new PlaceholderResolver())->transform($document, $context);
		$document = (new WidgetExpander($this->metric))->transform($document, $context);
		return (new GameRenderer($context, $this->metric, new BbmlStyler(), $this->bullet, $this->columns))->render($document);
	}

	public function getMetric(): TextMetric {
		return $this->metric;
	}

	public function getFormat(): string {
		return 'bbml';
	}
}
