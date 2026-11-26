<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

use AO\Markup\Context\{PlaceholderProvider, RenderContext};
use AO\Markup\{Document, Window};
use AO\Markup\Export\{Blocks, NativeRenderer, TextContent};
use AO\Markup\Node\{
	Align,
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
	ListItem,
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
	TableCell,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Widget\{Widget, WidgetNode};
use Closure;

/**
 * Renders a document as semantic HTML with aom-* classes and no inline theme colours
 *
 * The message comes first, then every window as a <section>. One instance renders
 * one document; placeholders must be resolved, and widgets without a native
 * renderer expanded.
 */
final class HtmlRenderer {
	/** @param Closure(Widget):?NativeRenderer<Widget> $native */
	public function __construct(
		private RenderContext $context,
		private Closure $native,
	) {
	}

	public function render(Document $document): string {
		$lines = ['<div class="aom-message">' . $this->content($document->body->children) . '</div>'];
		foreach ($document->windows as $window) {
			$lines []= $this->window($window);
		}
		return implode("\n", $lines);
	}

	private function window(Window $window): string {
		return implode("\n", [
			'<section class="aom-window" id="aom-' . Html::escape($window->id) . '">',
			'<h1 class="aom-title">' . Html::escape($window->title) . '</h1>',
			...$this->blocks($window->children),
			'</section>',
		]);
	}

	/**
	 * Content of a container: a single paragraph is written without <p>
	 *
	 * @param list<Node> $nodes
	 */
	private function content(array $nodes): string {
		$blocks = Blocks::flatten($nodes);
		if (count($blocks) === 1 && $blocks[0] instanceof Paragraph) {
			return $this->inline($blocks[0]->children);
		}
		if (count($blocks) === 0) {
			return '';
		}
		return "\n" . implode("\n", $this->blocks($blocks)) . "\n";
	}

	/**
	 * @param list<Node> $nodes
	 *
	 * @return list<string>
	 */
	private function blocks(array $nodes): array {
		$lines = [];
		foreach (Blocks::flatten($nodes) as $block) {
			$html = $this->block($block);
			if ($html !== '') {
				$lines []= $html;
			}
		}
		return $lines;
	}

	private function block(Node $node): string {
		return match (true) {
			$node instanceof Paragraph => $this->paragraph($node),
			$node instanceof Section => implode("\n", [
				'<section class="aom-section">',
				'<h2>' . Html::escape($node->title) . '</h2>',
				...$this->blocks($node->children),
				'</section>',
			]),
			$node instanceof Heading => ($node->level === 1 ? '<h3>' : '<h4>')
				. $this->inline($node->children)
				. ($node->level === 1 ? '</h3>' : '</h4>'),
			$node instanceof Indent => '<div class="aom-indent">' . $this->content($node->children) . '</div>',
			$node instanceof Center => '<div class="aom-center">' . $this->content($node->children) . '</div>',
			$node instanceof Footer => '<footer class="aom-footer">' . $this->content($node->children) . '</footer>',
			$node instanceof HorizontalRule => '<hr>',
			$node instanceof ListBlock => $this->list($node),
			$node instanceof FieldList => $this->fieldList($node),
			$node instanceof Table => $this->table($node),
			$node instanceof WidgetNode => $this->widget($node),
			default => $this->unsupported($node),
		};
	}

	private function paragraph(Paragraph $node): string {
		$html = $this->inline($node->children);
		return $html === '' ? '' : "<p>{$html}</p>";
	}

	private function unsupported(Node $node): string {
		$this->context->logger->warning('Cannot render {class} as HTML', ['class' => $node::class]);
		return $node instanceof Container ? implode("\n", $this->blocks($node->children)) : '';
	}

	private function widget(WidgetNode $node): string {
		$renderer = ($this->native)($node->widget);
		if ($renderer === null) {
			return $this->unsupported($node);
		}
		return $renderer->render($node, $this->context);
	}

	private function list(ListBlock $list): string {
		$open = $list->ordered
			? '<ol' . ($list->start !== 1 ? " start=\"{$list->start}\"" : '') . '>'
			: '<ul>';
		$lines = [$open];
		foreach ($list->items as $item) {
			$lines []= '<li>' . $this->icon($item) . $this->content($item->children) . '</li>';
		}
		$lines []= $list->ordered ? '</ol>' : '</ul>';
		return implode("\n", $lines);
	}

	private function icon(ListItem $item): string {
		return isset($item->icon) ? $this->inlineNode($item->icon) . ' ' : '';
	}

	private function fieldList(FieldList $list): string {
		$numeric = count($list->fields) > 0;
		foreach ($list->fields as $field) {
			foreach ($field->values as $value) {
				$numeric = $numeric && TextContent::isNumeric(...$value->children);
			}
		}
		$lines = ['<dl class="aom-fields' . ($numeric ? ' aom-numeric' : '') . '">'];
		foreach ($list->fields as $field) {
			$line = '<dt>' . $this->inline($field->name) . '</dt>';
			foreach ($field->values as $value) {
				$unit = isset($value->unit) ? ' <span class="aom-unit">' . Html::escape($value->unit) . '</span>' : '';
				$line .= '<dd>' . $this->inline($value->children) . "{$unit}</dd>";
			}
			$lines []= $line;
		}
		$lines []= '</dl>';
		return implode("\n", $lines);
	}

	private function table(Table $table): string {
		$head = [];
		$body = [];
		$inHead = true;
		foreach ($table->rows as $r => $row) {
			$inHead = $inHead && $row->isHeader();
			$cells = '';
			foreach ($row->cells as $c => $cell) {
				$cells .= $this->cell($cell, $table->alignmentOf($r, $c));
			}
			if ($inHead) {
				$head []= "<tr>{$cells}</tr>";
			} else {
				$body []= "<tr>{$cells}</tr>";
			}
		}
		$lines = ['<table class="aom-table' . ($table->border ? ' aom-border' : '') . '">'];
		if (count($head) > 0) {
			array_push($lines, '<thead>', ...$head);
			$lines []= '</thead>';
		}
		if (count($body) > 0) {
			array_push($lines, '<tbody>', ...$body);
			$lines []= '</tbody>';
		}
		$lines []= '</table>';
		return implode("\n", $lines);
	}

	private function cell(TableCell $cell, Align $align): string {
		$tag = $cell->header ? 'th' : 'td';
		$attributes = match ($align) {
			Align::Left => '',
			Align::Right => ' class="aom-right"',
			Align::Center => ' class="aom-center"',
		};
		if ($cell->colspan > 1) {
			$attributes .= " colspan=\"{$cell->colspan}\"";
		}
		return "<{$tag}{$attributes}>" . $this->content($cell->children) . "</{$tag}>";
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
			: Html::escape($default);
		return match (true) {
			$node instanceof Text => Html::escape($node->text),
			$node instanceof LineBreak => "<br>\n",
			$node instanceof Fragment => $this->inline($node->children),
			$node instanceof Style => $this->style($node),
			$node instanceof Color => '<span class="aom-color" style="color:' . Html::escape($node->hex) . '">'
				. $this->inline($node->children) . '</span>',
			$node instanceof ItemLink => self::link(
				'aom-item',
				$links->itemUrl($node->lowId, $node->highId, $node->ql),
				$children($node, "QL {$node->ql}")
			),
			$node instanceof NanoLink => self::link('aom-nano', $links->nanoUrl($node->id), $children($node)),
			$node instanceof SkillLink => '<span class="aom-skill" data-skill="' . $node->id . '">' . $children($node) . '</span>',
			$node instanceof CharLink => '<span class="aom-char" data-char="' . Html::escape($node->name) . '">'
				. $children($node, $node->name) . '</span>',
			$node instanceof CommandLink => $this->command($node),
			$node instanceof ClientCommandLink => '<span class="aom-client" data-cmd="' . Html::escape($node->command) . '">'
				. $children($node, $node->command) . '</span>',
			$node instanceof WaypointLink => "<span class=\"aom-waypoint\" data-x=\"{$node->x}\" data-y=\"{$node->y}\" data-pf=\"{$node->playfield}\">"
				. $children($node, "{$node->x}x{$node->y}") . '</span>',
			$node instanceof UrlLink => self::link('aom-url', $node->href, $children($node, $node->href)),
			$node instanceof PopupLink => '<a class="aom-popup" href="#aom-' . Html::escape($node->window) . '">'
				. $this->inline($node->children) . '</a>',
			$node instanceof Image => '<span class="aom-image" data-src="' . $node->source->value . ':' . Html::escape($node->id) . '"></span>',
			$node instanceof Placeholder => Html::escape($this->placeholder($node->name)),
			$node instanceof WidgetNode => $this->widget($node),
			default => $this->unsupportedInline($node),
		};
	}

	private function style(Style $node): string {
		$inner = $this->inline($node->children);
		return match ($node->style) {
			StyleName::Strong => "<strong class=\"aom-strong\">{$inner}</strong>",
			StyleName::Selected => "<strong class=\"aom-selected\">{$inner}</strong>",
			StyleName::Match => "<mark class=\"aom-match\">{$inner}</mark>",
			StyleName::Syntax => '<code class="aom-syntax">'
				. Html::escape($this->placeholder(PlaceholderProvider::SYMBOL)) . "{$inner}</code>",
			StyleName::Italic => "<i>{$inner}</i>",
			StyleName::Underline => "<u>{$inner}</u>",
			default => "<span class=\"aom-{$node->style->value}\">{$inner}</span>",
		};
	}

	private function command(CommandLink $node): string {
		$symbol = $this->placeholder(PlaceholderProvider::SYMBOL);
		$text = "{$symbol}{$node->command}";
		if ($node->show === CommandDisplay::Tell) {
			$bot = $this->placeholder(PlaceholderProvider::MY_NAME);
			$text = match ($node->via) {
				CommandChannel::Tell => "/tell {$bot} {$text}",
				CommandChannel::Priv => "/g {$bot} {$text}",
				CommandChannel::Org => "/o {$text}",
			};
		}
		$via = $node->via === CommandChannel::Tell ? '' : " data-via=\"{$node->via->value}\"";
		$label = count($node->children) > 0 ? $this->inline($node->children) : Html::escape($text);
		return '<a class="aom-cmd" data-cmd="' . Html::escape($node->command) . "\"{$via}>{$label}</a>";
	}

	private static function link(string $class, ?string $url, string $text): string {
		if ($url === null) {
			return "<span class=\"{$class}\">{$text}</span>";
		}
		return "<a class=\"{$class}\" href=\"" . Html::escape($url) . "\">{$text}</a>";
	}

	private function placeholder(string $name): string {
		$value = $this->context->placeholders->get($name);
		if ($value === null) {
			$this->context->logger->warning('No value for the placeholder "{name}"', ['name' => $name]);
		}
		return $value ?? '';
	}

	private function unsupportedInline(Node $node): string {
		$this->context->logger->warning('Cannot render {class} as HTML', ['class' => $node::class]);
		return $node instanceof Container ? $this->inline($node->children) : '';
	}
}
