<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

use AO\Markup\Context\{PlaceholderProvider, RenderContext};
use AO\Markup\{Document, Window};
use AO\Markup\Export\{Blocks, NativeRenderer, TextContent};
use AO\Markup\Export\Text\{MonospaceRenderer, PlainStyler};
use AO\Markup\Metric\MonospaceMetric;
use AO\Markup\Node\{
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Container,
	FieldList,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	Indent,
	ItemLink,
	LineBreak,
	ListBlock,
	NanoLink,
	Node,
	Paragraph,
	Placeholder,
	PopupLink,
	Section,
	SkillLink,
	Style,
	StyleName,
	Table,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Transform\WidgetExpander;
use AO\Markup\Widget\Builtin\Choice;
use AO\Markup\Widget\{Widget, WidgetNode};
use Closure;

/**
 * Renders a document as a Discord message: content, one embed per window, components
 *
 * One instance renders one document; placeholders must be resolved, and widgets
 * without a native renderer expanded.
 */
final class DiscordRenderer {
	private const RULE = '────────────────────';

	/** @var list<array<string,mixed>> Message components (select menus of choices) */
	private array $components = [];

	/** @param Closure(Widget):?NativeRenderer<Widget> $native */
	public function __construct(
		private RenderContext $context,
		private Closure $native,
	) {
	}

	/** @return array<string,mixed> The message as Discord expects it */
	public function render(Document $document): array {
		$message = ['content' => self::isSinglePopup($document) ? '' : $this->blocks($document->body->children)];
		$embeds = [];
		foreach ($document->windows as $window) {
			$embeds []= $this->embed($window);
		}
		if (count($embeds) > 0) {
			$message['embeds'] = $embeds;
		}
		if (count($this->components) > 0) {
			$message['components'] = $this->components;
		}
		return $message;
	}

	/** Is the message only a link to a window, which then is shown instead? */
	private static function isSinglePopup(Document $document): bool {
		$blocks = Blocks::flatten($document->body->children);
		if (count($blocks) !== 1 || !$blocks[0] instanceof Paragraph) {
			return false;
		}
		$links = array_values(array_filter(
			$blocks[0]->children,
			static fn (Node $node): bool => !$node instanceof Text || trim($node->text) !== ''
		));
		return count($links) === 1 && $links[0] instanceof PopupLink && $document->getWindow($links[0]->window) !== null;
	}

	/** @return array<string,mixed> */
	private function embed(Window $window): array {
		$embed = ['title' => $window->title];
		$blocks = Blocks::flatten($window->children);
		$footer = null;
		if (count($blocks) > 0 && $blocks[count($blocks) - 1] instanceof Footer) {
			$footer = array_pop($blocks);
		}

		// Sections become fields if the window is only an intro followed by sections
		$intro = [];
		while (count($blocks) > 0 && !$blocks[0] instanceof Section) {
			$intro []= array_shift($blocks);
		}
		$onlySections = count($blocks) > 0 && count(array_filter($blocks, static fn (Node $b): bool => !$b instanceof Section)) === 0;
		if ($onlySections) {
			if (count($intro) > 0) {
				$embed['description'] = $this->blocks($intro);
			}
			$embed['fields'] = [];
			foreach ($blocks as $section) {
				if ($section instanceof Section) {
					array_push($embed['fields'], ...$this->fields($section));
				}
			}
		} else {
			$description = $this->blocks([...$intro, ...$blocks]);
			if ($description !== '') {
				$embed['description'] = $description;
			}
		}
		if ($footer instanceof Footer) {
			$embed['footer'] = ['text' => $this->plain($footer->children)];
		}
		return $embed;
	}

	/**
	 * A section as embed field; values that are too long continue in further fields, like Nadybot does
	 *
	 * @return list<array{name:string,value:string}>
	 */
	private function fields(Section $section): array {
		$value = $this->blocks($section->children);
		$fields = [];
		foreach (Markdown::chunks($value === '' ? '_ _' : $value, Limits::FIELD_VALUE) as $i => $chunk) {
			$fields []= ['name' => $section->title . ($i > 0 ? ' (continued)' : ''), 'value' => $chunk];
		}
		return $fields;
	}

	/**
	 * Render blocks; empty lines between them, except after code blocks and before
	 * an indent that continues a paragraph
	 *
	 * @param list<Node> $nodes
	 */
	private function blocks(array $nodes, bool $compact=false): string {
		$result = '';
		$previous = null;
		$previousIsCode = false;
		foreach (Blocks::flatten($nodes) as $block) {
			$markdown = $this->block($block);
			if ($markdown === '') {
				continue;
			}
			if ($previous !== null) {
				$result .= ($compact || $previousIsCode || !Blocks::separated($previous, $block)) ? "\n" : "\n\n";
			}
			$result .= $markdown;
			$previous = $block;
			$previousIsCode = str_ends_with($markdown, '```');
		}
		return $result;
	}

	private function block(Node $node): string {
		return match (true) {
			$node instanceof Paragraph => $this->inline($node->children),
			$node instanceof Section => '**' . Markdown::escape($node->title) . "**\n" . $this->blocks($node->children),
			$node instanceof Heading => '**' . $this->inline($node->children) . '**',
			$node instanceof Indent => self::quote($this->blocks($node->children)),
			$node instanceof Center, $node instanceof Footer => $this->blocks($node->children),
			$node instanceof HorizontalRule => self::RULE,
			$node instanceof ListBlock => $this->list($node),
			$node instanceof FieldList => $this->fieldList($node),
			$node instanceof Table => Markdown::codeBlock($this->monospace([$node])),
			$node instanceof WidgetNode => $this->widget($node),
			default => $this->unsupported($node),
		};
	}

	private static function quote(string $markdown): string {
		return implode("\n", array_map(static fn (string $line): string => "> {$line}", explode("\n", $markdown)));
	}

	private function unsupported(Node $node): string {
		$this->context->logger->warning('Cannot render {class} for Discord', ['class' => $node::class]);
		return $node instanceof Container ? $this->blocks($node->children) : '';
	}

	private function widget(WidgetNode $node): string {
		$renderer = ($this->native)($node->widget);
		if ($renderer === null) {
			return $this->unsupported($node);
		}
		if ($node->widget instanceof Choice) {
			$this->components []= ChoiceRenderer::component($node, $this->context);
		}
		return $renderer->render($node, $this->context);
	}

	private function list(ListBlock $list): string {
		$lines = [];
		$number = $list->start;
		foreach ($list->items as $item) {
			$prefix = $list->ordered ? ($number++) . '. ' : '- ';
			$itemLines = explode("\n", $this->blocks($item->children, compact: true));
			$lines []= $prefix . array_shift($itemLines);
			foreach ($itemLines as $line) {
				$lines []= str_repeat(' ', strlen($prefix)) . $line;
			}
		}
		return implode("\n", $lines);
	}

	private function fieldList(FieldList $list): string {
		$lines = [];
		foreach ($list->fields as $field) {
			$values = [];
			foreach ($field->values as $value) {
				$text = $this->inline($value->children);
				$values []= ($text === '' ? '' : "**{$text}**")
					. (isset($value->unit) ? ' ' . Markdown::escape($value->unit) : '');
			}
			$name = $this->inline($field->name) . ':';
			$lines []= rtrim($name . ' ' . (array_shift($values) ?? ''));
			array_push($lines, ...$values);
		}
		return implode("\n", $lines);
	}

	/**
	 * Monospace layout of blocks, for code blocks
	 *
	 * @param list<Node> $nodes
	 */
	private function monospace(array $nodes): string {
		$metric = new MonospaceMetric();
		$document = (new WidgetExpander($metric))->transform(new Document(new Fragment(...$nodes)), $this->context);
		return (new MonospaceRenderer($this->context, $metric, new PlainStyler(), '•'))->render($document);
	}

	/**
	 * Plain text without markdown, e.g. for the embed footer
	 *
	 * @param list<Node> $nodes
	 */
	private function plain(array $nodes): string {
		return rtrim($this->monospace($nodes), "\n");
	}

	/** @param list<Node> $nodes */
	private function inline(array $nodes): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= $this->inlineNode($node);
		}
		return $result;
	}

	private function inlineNode(Node $node): string {
		$links = $this->context->links;
		$children = fn (Container $node, string $default=''): string => count($node->children) > 0
			? $this->inline($node->children)
			: Markdown::escape($default);
		$plain = static fn (Container $node, string $default=''): string => count($node->children) > 0
			? TextContent::of(...$node->children)
			: $default;
		return match (true) {
			$node instanceof Text => Markdown::escape($node->text),
			$node instanceof LineBreak => "\n",
			$node instanceof Fragment, $node instanceof Color => $this->inline($node->children),
			$node instanceof Style => $this->style($node),
			$node instanceof ItemLink => self::link(
				$children($node, "QL {$node->ql}"),
				$links->itemUrl($node->lowId, $node->highId, $node->ql)
			),
			$node instanceof NanoLink => self::link($children($node), $links->nanoUrl($node->id)),
			$node instanceof SkillLink, $node instanceof PopupLink => $children($node),
			$node instanceof CharLink => $children($node, $node->name),
			$node instanceof WaypointLink => $children($node, "{$node->x}x{$node->y}"),
			$node instanceof CommandLink => Markdown::code($plain($node, $this->commandText($node))),
			$node instanceof ClientCommandLink => Markdown::code($plain($node, $node->command)),
			$node instanceof UrlLink => count($node->children) === 0 || TextContent::of(...$node->children) === $node->href
				? $node->href
				: self::link($this->inline($node->children), $node->href),
			$node instanceof Image => '',
			$node instanceof Placeholder => Markdown::escape($this->placeholder($node->name)),
			$node instanceof WidgetNode => $this->widget($node),
			default => $this->unsupportedInline($node),
		};
	}

	private function style(Style $node): string {
		$inner = $this->inline($node->children);
		if ($inner === '') {
			return '';
		}
		return match ($node->style) {
			StyleName::Strong, StyleName::Selected => "**{$inner}**",
			StyleName::Match, StyleName::Underline => "__{$inner}__",
			StyleName::Italic => "*{$inner}*",
			StyleName::Syntax => Markdown::code($this->placeholder(PlaceholderProvider::SYMBOL) . TextContent::of(...$node->children)),
			default => $inner,
		};
	}

	/** The command as the user would type it */
	private function commandText(CommandLink $node): string {
		$command = $this->placeholder(PlaceholderProvider::SYMBOL) . $node->command;
		if ($node->show === CommandDisplay::Command) {
			return $command;
		}
		$bot = $this->placeholder(PlaceholderProvider::MY_NAME);
		return match ($node->via) {
			CommandChannel::Tell => "/tell {$bot} {$command}",
			CommandChannel::Priv => "/g {$bot} {$command}",
			CommandChannel::Org => "/o {$command}",
		};
	}

	private static function link(string $text, ?string $url): string {
		return isset($url) ? "[{$text}]({$url})" : $text;
	}

	private function placeholder(string $name): string {
		$value = $this->context->placeholders->get($name);
		if ($value === null) {
			$this->context->logger->warning('No value for the placeholder "{name}"', ['name' => $name]);
		}
		return $value ?? '';
	}

	private function unsupportedInline(Node $node): string {
		$this->context->logger->warning('Cannot render {class} for Discord', ['class' => $node::class]);
		return $node instanceof Container ? $this->inline($node->children) : '';
	}
}
