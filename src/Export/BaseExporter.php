<?php declare(strict_types=1);

namespace AO\Markup\Export;

use AO\Markup\Widget\Widget;

abstract class BaseExporter implements Exporter {
	/** @var array<class-string<Widget>,NativeRenderer<Widget>> */
	private array $nativeRenderers = [];

	/** @param NativeRenderer<Widget> $renderer */
	public function addNativeRenderer(NativeRenderer $renderer): static {
		$this->nativeRenderers[$renderer->getWidgetClass()] = $renderer;
		return $this;
	}

	/** @return list<class-string<Widget>> The widgets this exporter renders natively */
	protected function getNativeWidgets(): array {
		return array_keys($this->nativeRenderers);
	}

	/** @return ?NativeRenderer<Widget> */
	protected function getNativeRenderer(Widget $widget): ?NativeRenderer {
		return $this->nativeRenderers[$widget::class] ?? null;
	}
}
