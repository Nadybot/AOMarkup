<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Context\RenderContext;
use AO\Markup\Widget\WidgetNode;

/**
 * Renders a widget natively in one specific exporter, instead of its fallback
 *
 * @template T of \AO\Markup\Widget\Widget
 */
interface NativeRenderer {
	/** @return class-string<T> */
	public function getWidgetClass(): string;

	public function render(WidgetNode $node, RenderContext $context): string;
}
