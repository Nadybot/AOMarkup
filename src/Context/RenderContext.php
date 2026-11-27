<?php declare(strict_types=1);

namespace AO\Markup\Context;

use Psr\Log\{LoggerInterface, NullLogger};

/** Everything an exporter needs to know about the bot it renders for */
final class RenderContext {
	public function __construct(
		public readonly PlaceholderProvider $placeholders,
		public readonly Theme $theme=new DefaultTheme(),
		public readonly LinkResolver $links=new TemplateLinkResolver(),
		public readonly LoggerInterface $logger=new NullLogger(),
		public readonly TimeFormatter $time=new DefaultTimeFormatter(),
	) {
	}
}
