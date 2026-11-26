<?php declare(strict_types=1);

namespace AO\Markup\Parser;

use function Safe\{preg_match, preg_split};

use AO\Markup\{Document, Window};
use AO\Markup\Import\Importer;
use AO\Markup\Node\{
	Align,
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Field,
	FieldList,
	FieldValue,
	Footer,
	Fragment,
	Heading,
	HorizontalRule,
	Image,
	ImageSource,
	Indent,
	ItemLink,
	Keep,
	LineBreak,
	ListBlock,
	ListItem,
	NanoLink,
	Node,
	PageBreak,
	Paragraph,
	Placeholder,
	PopupLink,
	Repeat,
	Section,
	SkillLink,
	Style,
	StyleName,
	Table,
	TableCell,
	TableRow,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Widget\{WidgetNode, WidgetRegistry};
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Parser for AoXML, see docs/AoXML.md
 *
 * Never throws on bad input: it recovers and reports problems as warnings.
 * <window>s end up in Document::$windows, <popup>s link to them.
 *
 * Every block container (the body, windows, sections, list items, table cells, …)
 * contains block nodes only; inline content in it is wrapped in Paragraphs.
 */
final class AoXmlParser implements Importer {
	/** Elements that never have content; they may be written without the slash */
	private const VOID = ['br', 'hr', 'pagebreak', 'myname', 'myguild', 'symbol', 'var', 'img'];

	/** Elements that start on their own line */
	private const BLOCK = [
		'section', 'dl', 'dt', 'dd', 'table', 'tr', 'th', 'td', 'ul', 'ol', 'li', 'indent',
		'h1', 'h2', 'center', 'hr', 'pagebreak', 'keep', 'repeat', 'footer', 'window',
	];

	/** Elements that flow within a line, apart from the named styles */
	private const INLINE = [
		'color', 'item', 'nano', 'skill', 'char', 'waypoint', 'cmd', 'client', 'url', 'img', 'br',
		'myname', 'myguild', 'symbol', 'var', 'popup',
	];

	/** Hints for elements people bring along from BBML or HTML */
	private const HINTS = [
		'a' => 'AoXML has no <a>, use <popup>, <cmd>, <client>, <url>, <item>, … instead',
		'highlight' => 'use <strong>',
		'end' => 'close the element instead, e.g. </strong>',
		'tab' => 'use <indent>, <section>, <field> or <table>',
		'font' => 'use <color value="#RRGGBB">',
		'b' => 'use <strong>',
		'header' => 'the window title comes from <window title>',
		'header2' => 'use <section title>',
	];

	/** @var array<string,Window> Windows by id, in document order */
	private array $windows = [];

	/** @var array<string,int> Referenced window ids and the line of the first reference */
	private array $refs = [];

	/** Currently parsing the content of a window */
	private bool $inWindow = false;

	/** @var list<Node> Content of the <footer>s of the window being parsed */
	private array $footer = [];

	public function __construct(
		private WidgetRegistry $widgets=new WidgetRegistry(),
		private LoggerInterface $logger=new NullLogger(),
	) {
	}

	public function parse(string $input): Document {
		$this->windows = [];
		$this->refs = [];
		$this->inWindow = false;

		$root = $this->buildTree((new Tokenizer($this->logger))->tokenize($input));
		$body = $this->blocks($root->children, true);

		$this->reportMissingWindows();
		return new Document(new Fragment(...$body), $this->windows);
	}

	public function import(string $input): Document {
		return $this->parse($input);
	}

	private function reportMissingWindows(): void {
		foreach ($this->refs as $ref => $line) {
			if (!isset($this->windows[$ref])) {
				$this->warn($line, "<popup window=\"{$ref}\"> refers to a window that does not exist");
			}
		}
	}

	// ------------------------------------------------------------------
	// Raw tree
	// ------------------------------------------------------------------

	/** @param list<Token> $tokens */
	private function buildTree(array $tokens): RawElement {
		$root = new RawElement('#root', [], 1);

		/** @var list<RawElement> */
		$stack = [$root];
		foreach ($tokens as $token) {
			$parent = $stack[count($stack)-1];
			if ($token->type === TokenType::Text) {
				$parent->children []= $token->text;
				continue;
			}
			if (!$this->isKnown($token->name)) {
				$hint = isset(self::HINTS[$token->name]) ? '; ' . self::HINTS[$token->name] : '';
				$this->warn($token->line, "Unknown element <{$token->name}> taken as text{$hint}");
				$parent->children []= $token->text;
				continue;
			}
			if ($token->type === TokenType::Open) {
				$element = new RawElement($token->name, $token->attributes, $token->line);
				$parent->children []= $element;
				if (!$token->selfClosing && !$this->isVoid($token->name)) {
					$stack []= $element;
				}
				continue;
			}

			// Closing tag
			if ($this->isVoid($token->name)) {
				$this->warn($token->line, "</{$token->name}> ignored, <{$token->name}> has no content");
				continue;
			}
			$depth = null;
			for ($i = count($stack) - 1; $i > 0; $i--) {
				if ($stack[$i]->name === $token->name) {
					$depth = $i;
					break;
				}
			}
			if ($depth === null) {
				$this->warn($token->line, "</{$token->name}> ignored, there is no open <{$token->name}>");
				continue;
			}
			for ($i = count($stack) - 1; $i > $depth; $i--) {
				$this->warn($stack[$i]->line, "<{$stack[$i]->name}> is not closed, closing it at </{$token->name}> on line {$token->line}");
			}
			$stack = array_slice($stack, 0, $depth);
		}
		for ($i = count($stack) - 1; $i > 0; $i--) {
			$this->warn($stack[$i]->line, "<{$stack[$i]->name}> is not closed");
		}
		return $root;
	}

	private function isKnown(string $name): bool {
		return in_array($name, self::BLOCK, true)
			|| in_array($name, self::INLINE, true)
			|| StyleName::tryFrom($name) !== null
			|| $this->widgets->has($name);
	}

	private function isVoid(string $name): bool {
		return in_array($name, self::VOID, true) || ($this->widgets->definition($name)->void ?? false);
	}

	private function isBlock(string $name): bool {
		return in_array($name, self::BLOCK, true) || ($this->widgets->definition($name)->block ?? false);
	}

	// ------------------------------------------------------------------
	// Block content
	// ------------------------------------------------------------------

	/**
	 * Convert the content of a block container
	 *
	 * @param list<RawElement|string> $items
	 * @param bool                    $root  True for the top level of the message
	 *
	 * @return list<Node>
	 */
	private function blocks(array $items, bool $root=false): array {
		$result = [];
		$run = [];
		foreach ($items as $item) {
			if ($item instanceof RawElement && $this->isBlock($item->name)) {
				array_push($result, ...$this->paragraphs($run));
				$run = [];
				array_push($result, ...$this->block($item, $root));
				continue;
			}
			$run []= $item;
		}
		array_push($result, ...$this->paragraphs($run));
		return $result;
	}

	/**
	 * Turn one run of inline content into paragraphs, split at empty lines
	 *
	 * @param list<RawElement|string> $run
	 *
	 * @return list<Paragraph>
	 */
	private function paragraphs(array $run): array {
		if (count($run) === 0) {
			return [];
		}
		$paragraphs = [];
		$current = [];
		foreach ($this->flow($run, new FlowState(), true) as $node) {
			if ($node instanceof ParagraphBreak) {
				if (count($current) > 0) {
					$paragraphs []= new Paragraph(...$current);
				}
				$current = [];
				continue;
			}
			$current []= $node;
		}
		if (count($current) > 0) {
			$paragraphs []= new Paragraph(...$current);
		}
		return $paragraphs;
	}

	/** @return list<Node> */
	private function block(RawElement $element, bool $root=false): array {
		$nodes = match ($element->name) {
			'section' => [new Section($this->string($element, 'title', ''), ...$this->blocks($element->children))],
			'dl' => [$this->fieldList($element)],
			'h1', 'h2' => [new Heading($element->name === 'h1' ? 1 : 2, ...$this->inline($element->children))],
			'table' => [$this->table($element)],
			'ul', 'ol' => [$this->list($element)],
			'indent' => [new Indent(...$this->blocks($element->children))],
			'center' => [new Center(...$this->blocks($element->children))],
			'keep' => [new Keep(...$this->blocks($element->children))],
			'repeat' => [new Repeat(...$this->blocks($element->children))],
			'hr' => [new HorizontalRule()],
			'pagebreak' => [new PageBreak()],
			'window' => $this->window($element, $root),
			'footer' => $this->footer($element),
			'tr', 'th', 'td', 'li', 'dt', 'dd' => $this->misplaced($element),
			default => $this->widget($element, true),
		};
		$this->checkAttributes($element);
		return $nodes;
	}

	/** @return list<Node> */
	private function misplaced(RawElement $element): array {
		$parent = match ($element->name) {
			'th', 'td', 'tr' => 'table',
			'dt', 'dd' => 'dl',
			default => 'ul> or <ol',
		};
		$this->warn($element->line, "<{$element->name}> outside of <{$parent}>, using its content only");
		foreach (array_keys($element->attributes) as $attribute) {
			$element->get($attribute);
		}
		return $this->blocks($element->children);
	}

	/** @return list<Node> Always empty: the window goes into Document::$windows */
	private function window(RawElement $element, bool $root): array {
		if (!$root) {
			$this->warn($element->line, '<window> is only allowed at the top level, not inside another element');
		}
		$id = $this->optionalString($element, 'id');
		$title = $this->string($element, 'title', '');
		if ($id === null) {
			$this->warn($element->line, '<window> without id cannot be opened, ignoring it');
			return [];
		}
		if (isset($this->windows[$id])) {
			$this->warn($element->line, "Duplicate window id \"{$id}\", ignoring this window");
			return [];
		}
		[$inWindow, $footer] = [$this->inWindow, $this->footer];
		[$this->inWindow, $this->footer] = [true, []];
		$children = $this->blocks($element->children);
		array_push($children, ...$this->collectedFooter());
		[$this->inWindow, $this->footer] = [$inWindow, $footer];
		$this->windows[$id] = new Window($id, $title, ...$children);
		return [];
	}

	/** @return list<Footer> The footer collected for the current window, if any */
	private function collectedFooter(): array {
		return count($this->footer) > 0 ? [new Footer(...$this->footer)] : [];
	}

	/**
	 * Collect a <footer>; the window adds it at its end
	 *
	 * @return list<Node> Empty in a window, a paragraph outside of one
	 */
	private function footer(RawElement $element): array {
		$content = $this->inline($element->children);
		if (!$this->inWindow) {
			$this->warn($element->line, '<footer> is only allowed inside a <window>, showing it as a paragraph');
			return count($content) > 0 ? [new Paragraph(...$content)] : [];
		}
		if (count($this->footer) > 0 && count($content) > 0) {
			$this->footer []= new LineBreak();
		}
		array_push($this->footer, ...$content);
		return [];
	}

	/** A <dl>: every <dt> starts a field, the following <dd>s are its values */
	private function fieldList(RawElement $element): FieldList {
		/** @var list<list<Node>> */
		$names = [];

		/** @var list<list<FieldValue>> */
		$values = [];

		/** @var list<int> */
		$lines = [];
		foreach ($this->elementOnly($element) as $child) {
			if ($child instanceof RawElement && $child->name === 'dt') {
				$names []= $this->inline($child->children);
				$values []= [];
				$lines []= $child->line;
				$this->checkAttributes($child);
				continue;
			}
			if (count($names) === 0) {
				$this->warn($element->line, '<dl> must start with a <dt>, using a field without name');
				$names []= [];
				$values []= [];
				$lines []= $element->line;
			}
			$last = count($values) - 1;
			if ($child instanceof RawElement && $child->name === 'dd') {
				$values[$last] []= new FieldValue($this->optionalString($child, 'unit'), ...$this->inline($child->children));
				$this->checkAttributes($child);
				continue;
			}
			$this->warn($element->line, 'Content of <dl> outside of <dt> or <dd>, using it as a value');
			$values[$last] []= new FieldValue(null, ...$this->inline([$child]));
		}
		$fields = [];
		foreach ($names as $i => $name) {
			if (count($values[$i]) === 0) {
				$this->warn($lines[$i], '<dt> without <dd>');
			}
			$fields []= new Field($name, ...$values[$i]);
		}
		return new FieldList(...$fields);
	}

	private function table(RawElement $element): Table {
		$border = $this->bool($element, 'border');
		$rows = [];
		$stray = [];
		foreach ($this->elementOnly($element) as $child) {
			if ($child instanceof RawElement && $child->name === 'tr') {
				if (count($stray) > 0) {
					$rows []= $this->row($stray, $element->line);
					$stray = [];
				}
				$rows []= $this->row($child->children, $child->line);
				$this->checkAttributes($child);
				continue;
			}
			$stray []= $child;
		}
		if (count($stray) > 0) {
			$this->warn($element->line, 'Content of <table> outside of <tr>, wrapping it in a row');
			$rows []= $this->row($stray, $element->line);
		}
		return new Table($border, ...$rows);
	}

	/** @param list<RawElement|string> $items */
	private function row(array $items, int $line): TableRow {
		$cells = [];
		$stray = [];
		foreach ($items as $item) {
			if (is_string($item) && trim($item) === '') {
				continue;
			}
			if ($item instanceof RawElement && ($item->name === 'td' || $item->name === 'th')) {
				array_push($cells, ...$this->strayCell($stray, $line));
				$stray = [];
				$cells []= $this->cell($item);
				continue;
			}
			$stray []= $item;
		}
		array_push($cells, ...$this->strayCell($stray, $line));
		return new TableRow(...$cells);
	}

	/**
	 * Wrap content of a row that is not in a cell into a cell
	 *
	 * @param list<RawElement|string> $stray
	 *
	 * @return list<TableCell>
	 */
	private function strayCell(array $stray, int $line): array {
		if (count($stray) === 0) {
			return [];
		}
		$this->warn($line, 'Content of <tr> outside of <td> or <th>, wrapping it in a cell');
		return [new TableCell(false, null, 1, ...$this->blocks($stray))];
	}

	private function cell(RawElement $element): TableCell {
		$align = null;
		$value = $this->optionalString($element, 'align');
		if ($value !== null) {
			$align = Align::tryFrom($value);
			if ($align === null) {
				$this->warn($element->line, "Invalid align=\"{$value}\" on <{$element->name}>, use left, right or center");
			}
		}
		$colspan = $this->int($element, 'colspan') ?? 1;
		if ($colspan < 1) {
			$this->warn($element->line, "Invalid colspan=\"{$colspan}\" on <{$element->name}>");
			$colspan = 1;
		}
		$cell = new TableCell($element->name === 'th', $align, $colspan, ...$this->blocks($element->children));
		$this->checkAttributes($element);
		return $cell;
	}

	private function list(RawElement $element): ListBlock {
		$ordered = $element->name === 'ol';
		$start = $ordered ? ($this->int($element, 'start') ?? 1) : 1;
		$items = [];
		$stray = [];
		foreach ($this->elementOnly($element) as $child) {
			if ($child instanceof RawElement && $child->name === 'li') {
				if (count($stray) > 0) {
					$items []= new ListItem(null, ...$this->blocks($stray));
					$stray = [];
				}
				$icon = null;
				$src = $this->optionalString($child, 'icon');
				if ($src !== null) {
					$icon = $this->image($src, $child->line);
				}
				$items []= new ListItem($icon, ...$this->blocks($child->children));
				$this->checkAttributes($child);
				continue;
			}
			$this->warn($element->line, "Content of <{$element->name}> outside of <li>, wrapping it in a list item");
			$stray []= $child;
		}
		if (count($stray) > 0) {
			$items []= new ListItem(null, ...$this->blocks($stray));
		}
		return new ListBlock($ordered, $start, ...$items);
	}

	/**
	 * Children of an element-only container, without the whitespace between them
	 *
	 * @return list<RawElement|string>
	 */
	private function elementOnly(RawElement $element): array {
		return array_values(array_filter(
			$element->children,
			static fn (RawElement|string $child): bool => !is_string($child) || trim($child) !== ''
		));
	}

	// ------------------------------------------------------------------
	// Inline content
	// ------------------------------------------------------------------

	/**
	 * Convert content that is inline only, like the value of a field
	 *
	 * @param list<RawElement|string> $items
	 *
	 * @return list<Node>
	 */
	private function inline(array $items): array {
		$nodes = [];
		foreach ($this->flow($items, new FlowState(), false) as $node) {
			$nodes []= $node instanceof ParagraphBreak ? new LineBreak() : $node;
		}
		return $nodes;
	}

	/**
	 * Apply the whitespace rules to inline content and convert its elements
	 *
	 * @param list<RawElement|string> $items
	 * @param bool                    $top   True at the top of a run, where an empty line is a paragraph break
	 *
	 * @return list<Node|ParagraphBreak>
	 */
	private function flow(array $items, FlowState $state, bool $top): array {
		$out = [];
		$buffer = '';
		foreach ($items as $item) {
			if (is_string($item)) {
				$pieces = preg_split('/(\n|[ \t]+)/', $item, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
				foreach ($pieces as $piece) {
					if ($piece === "\n") {
						$state->newlines++;
						$state->space = false;
						$state->lineStart = true;
					} elseif (trim($piece, " \t") === '') {
						if (!$state->lineStart) {
							$state->space = true;
						}
					} else {
						$this->emitPending($state, $top, $out, $buffer);
						$buffer .= $piece;
						$state->lineStart = false;
						$state->hasContent = true;
					}
				}
				continue;
			}
			$saved = [$state->newlines, $state->space, $buffer, count($out)];
			$this->emitPending($state, $top, $out, $buffer);
			if ($buffer !== '') {
				$out []= new Text($buffer);
				$buffer = '';
			}
			$nodes = $this->inlineElement($item, $state);
			$this->checkAttributes($item);
			if (count($nodes) === 0) {
				// The element shows nothing: the whitespace before it is still pending
				[$state->newlines, $state->space, $buffer] = $saved;
				array_splice($out, $saved[3]);
				continue;
			}
			array_push($out, ...$nodes);
			$state->lineStart = false;
			$state->hasContent = true;
		}
		if ($buffer !== '') {
			$out []= new Text($buffer);
		}
		return self::mergeTexts($out);
	}

	/**
	 * Merge adjacent Text nodes, e.g. after an invalid element was replaced by its content
	 *
	 * @param list<Node|ParagraphBreak> $nodes
	 *
	 * @return list<Node|ParagraphBreak>
	 */
	private static function mergeTexts(array $nodes): array {
		$result = [];
		$last = null;
		foreach ($nodes as $node) {
			if ($node instanceof Text && $last instanceof Text) {
				$node = new Text($last->text . $node->text);
				array_pop($result);
			}
			$result []= $node;
			$last = $node;
		}
		return $result;
	}

	/**
	 * Emit the whitespace that is due before the next content
	 *
	 * @param list<Node|ParagraphBreak> $out
	 */
	private function emitPending(FlowState $state, bool $top, array &$out, string &$buffer): void {
		if ($state->hasContent) {
			if ($state->newlines > 0) {
				if ($buffer !== '') {
					$out []= new Text($buffer);
					$buffer = '';
				}
				if ($state->newlines === 1) {
					$out []= new LineBreak();
				} elseif ($top) {
					$out []= new ParagraphBreak();
				} else {
					$out []= new LineBreak();
					$out []= new LineBreak();
				}
			} elseif ($state->space) {
				$buffer .= ' ';
			}
		}
		$state->newlines = 0;
		$state->space = false;
	}

	/** @return list<Node> */
	private function inlineElement(RawElement $element, FlowState $state): array {
		$style = StyleName::tryFrom($element->name);
		if ($style !== null) {
			return [new Style($style, ...$this->content($element, $state))];
		}
		if ($element->name === 'window') {
			return $this->window($element, false);
		}
		if ($this->isBlock($element->name) && $this->widgets->has($element->name)) {
			$this->warn($element->line, "Block widget <{$element->name}> inside a line, using its content only");
			foreach (array_keys($element->attributes) as $attribute) {
				$element->get($attribute);
			}
			$definition = $this->widgets->definition($element->name);
			return $definition !== null && $definition->only !== null
				? $this->onlyChildren($element, $definition->only)
				: $this->content($element, $state);
		}
		if ($this->isBlock($element->name) && !$this->widgets->has($element->name)) {
			$this->warn($element->line, "Block element <{$element->name}> inside a line, using its content only");
			foreach (array_keys($element->attributes) as $attribute) {
				$element->get($attribute);
			}
			return $this->content($element, $state);
		}
		return match ($element->name) {
			'br' => [new LineBreak()],
			'myname', 'myguild', 'symbol' => [new Placeholder($element->name)],
			'var' => $this->variable($element),
			'color' => $this->color($element, $state),
			'item' => $this->item($element, $state),
			'nano', 'skill' => $this->idLink($element, $state),
			'char' => $this->char($element, $state),
			'waypoint' => $this->waypoint($element, $state),
			'cmd' => $this->command($element, $state),
			'client' => $this->client($element, $state),
			'url' => $this->url($element, $state),
			'img' => $this->img($element),
			'popup' => $this->popup($element, $state),
			default => $this->widget($element, false, $state),
		};
	}

	/**
	 * Content of an inline element, continuing the flow of its parent
	 *
	 * @return list<Node>
	 */
	private function content(RawElement $element, FlowState $state): array {
		$nodes = [];
		foreach ($this->flow($element->children, $state, false) as $node) {
			$nodes []= $node instanceof ParagraphBreak ? new LineBreak() : $node;
		}
		return $nodes;
	}

	/** @return list<Node> */
	private function variable(RawElement $element): array {
		$name = $this->string($element, 'name', '');
		return $name === '' ? [] : [new Placeholder($name)];
	}

	/** @return list<Node> */
	private function color(RawElement $element, FlowState $state): array {
		$value = $this->string($element, 'value', '');
		$children = $this->content($element, $state);
		$m = self::match('/^#?([0-9A-Fa-f]{6})$/', $value);
		if ($m === null) {
			$this->warn($element->line, "Invalid colour \"{$value}\" on <color>, use #RRGGBB");
			return $children;
		}
		return [new Color('#' . strtoupper($m[1]), ...$children)];
	}

	/** @return list<Node> */
	private function item(RawElement $element, FlowState $state): array {
		$id = $this->string($element, 'id', '');
		$children = $this->content($element, $state);
		$m = self::match('/^(\d+)(?:\/(\d+))?$/', $id);
		if ($m === null) {
			$this->warn($element->line, "Invalid id=\"{$id}\" on <item>, use id=\"lowid/highid\" or id=\"id\"");
			return $children;
		}
		$ql = $this->int($element, 'ql');
		if ($ql === null) {
			$this->warn($element->line, '<item> without ql, using QL 1');
			$ql = 1;
		}
		$low = (int)$m[1];
		$high = isset($m[2]) ? (int)$m[2] : $low;
		return [new ItemLink($low, $high, $ql, ...$children)];
	}

	/** @return list<Node> */
	private function idLink(RawElement $element, FlowState $state): array {
		$id = $this->int($element, 'id');
		$children = $this->content($element, $state);
		if ($id === null) {
			$this->warn($element->line, "<{$element->name}> needs a numeric id");
			return $children;
		}
		return [$element->name === 'nano' ? new NanoLink($id, ...$children) : new SkillLink($id, ...$children)];
	}

	/** @return list<Node> */
	private function char(RawElement $element, FlowState $state): array {
		$name = $this->optionalString($element, 'name');
		if ($name !== null) {
			return [new CharLink($name, ...$this->content($element, $state))];
		}
		$name = $this->plainText($element);
		if ($name === '') {
			$this->warn($element->line, '<char> without name');
			return [];
		}
		return [new CharLink($name)];
	}

	/** @return list<Node> */
	private function command(RawElement $element, FlowState $state): array {
		$show = CommandDisplay::Command;
		$value = $this->optionalString($element, 'show');
		if ($value !== null) {
			$show = CommandDisplay::tryFrom($value) ?? CommandDisplay::Command;
			if (CommandDisplay::tryFrom($value) === null) {
				$this->warn($element->line, "Invalid show=\"{$value}\" on <cmd>, use command or tell");
			}
		}
		$via = CommandChannel::Tell;
		$value = $this->optionalString($element, 'via');
		if ($value !== null) {
			$via = CommandChannel::tryFrom($value) ?? CommandChannel::Tell;
			if (CommandChannel::tryFrom($value) === null) {
				$this->warn($element->line, "Invalid via=\"{$value}\" on <cmd>, use tell, priv or org");
			}
		}
		$command = $this->optionalString($element, 'command');
		if ($command !== null) {
			return [new CommandLink($command, $show, $via, ...$this->content($element, $state))];
		}
		$command = $this->plainText($element);
		if ($command === '') {
			$this->warn($element->line, '<cmd> without command');
			return [];
		}
		return [new CommandLink($command, $show, $via)];
	}

	/** @return list<Node> */
	private function client(RawElement $element, FlowState $state): array {
		$command = $this->optionalString($element, 'command');
		if ($command !== null) {
			return [new ClientCommandLink($command, ...$this->content($element, $state))];
		}
		$command = $this->plainText($element);
		if ($command === '') {
			$this->warn($element->line, '<client> without command');
			return [];
		}
		return [new ClientCommandLink($command)];
	}

	/** @return list<Node> */
	private function waypoint(RawElement $element, FlowState $state): array {
		$x = $this->int($element, 'x');
		$y = $this->int($element, 'y');
		$playfield = $this->int($element, 'pf');
		$children = $this->content($element, $state);
		if ($x === null || $y === null || $playfield === null) {
			$this->warn($element->line, '<waypoint> needs numeric x, y and pf');
			return $children;
		}
		return [new WaypointLink($x, $y, $playfield, ...$children)];
	}

	/** @return list<Node> */
	private function url(RawElement $element, FlowState $state): array {
		$href = $this->string($element, 'href', '');
		$children = $this->content($element, $state);
		if ($href === '') {
			$this->warn($element->line, '<url> without href');
			return $children;
		}
		return [new UrlLink($href, ...$children)];
	}

	/** @return list<Node> */
	private function img(RawElement $element): array {
		$image = $this->image($this->string($element, 'src', ''), $element->line);
		return $image === null ? [] : [$image];
	}

	private function image(string $src, int $line): ?Image {
		$m = self::match('/^(rdb|tdb):(.+)$/', $src);
		if ($m === null || ($m[1] === 'rdb' && !ctype_digit($m[2]))) {
			$this->warn($line, "Invalid image \"{$src}\", use rdb:<number> or tdb:<name>");
			return null;
		}
		return new Image(ImageSource::from($m[1]), $m[2]);
	}

	/** @return list<Node> */
	private function popup(RawElement $element, FlowState $state): array {
		$window = $this->string($element, 'window', '');
		$children = $this->content($element, $state);
		if ($this->inWindow) {
			$this->warn($element->line, 'Windows cannot be nested, <popup> inside a <window> is shown as text');
			return $children;
		}
		if ($window === '') {
			return $children;
		}
		$this->refs[$window] ??= $element->line;
		return [new PopupLink($window, ...$children)];
	}

	/** @return list<Node> */
	private function widget(RawElement $element, bool $block, ?FlowState $state=null): array {
		$definition = $this->widgets->definition($element->name);
		$children = [];
		if ($definition !== null && $definition->only !== null) {
			$children = $this->onlyChildren($element, $definition->only);
		} elseif ($definition !== null && !$definition->void) {
			$children = ($block || $state === null)
				? $this->blocks($element->children)
				: $this->content($element, $state);
		}
		$attributes = [];
		foreach (array_keys($element->attributes) as $attribute) {
			$value = $element->get($attribute);
			if (is_string($value) || $value === true) {
				$attributes[$attribute] = $value;
			}
		}
		$logger = new LinePrefixLogger($this->logger, $element->line);
		$node = $this->widgets->create($element->name, $attributes, $logger, ...$children);
		if ($node === null) {
			$this->warn($element->line, "<{$element->name}> dropped");
			// The children of an element-only widget are inline; in a block container they need a paragraph
			if ($block && $definition !== null && $definition->only !== null && count($children) > 0) {
				return [new Paragraph(...$children)];
			}
			return $children;
		}
		return [$node];
	}

	/**
	 * Children of a widget that only allows certain child elements
	 *
	 * @param list<string> $allowed
	 *
	 * @return list<Node>
	 */
	private function onlyChildren(RawElement $element, array $allowed): array {
		$children = [];
		foreach ($this->elementOnly($element) as $child) {
			if (!($child instanceof RawElement) || !in_array($child->name, $allowed, true)) {
				$allowedTags = implode(', ', array_map(static fn (string $name): string => "<{$name}>", $allowed));
				$this->warn($element->line, "<{$element->name}> may only contain {$allowedTags}, dropping the rest");
				continue;
			}
			$converted = $this->widgets->has($child->name)
				? $this->widget($child, false, new FlowState())
				: $this->inlineElement($child, new FlowState());
			foreach ($converted as $node) {
				if ($node instanceof WidgetNode) {
					$children []= $node;
				}
			}
		}
		return $children;
	}

	/** The text content of an element whose content must be plain text */
	private function plainText(RawElement $element): string {
		if ($element->hasElementChildren()) {
			$this->warn($element->line, "<{$element->name}> may only contain text, ignoring the tags in it");
		}
		return trim(\Safe\preg_replace('/\s+/', ' ', $element->textContent()));
	}

	// ------------------------------------------------------------------
	// Attributes
	// ------------------------------------------------------------------

	private function optionalString(RawElement $element, string $attribute): ?string {
		$value = $element->get($attribute);
		if ($value === true) {
			$this->warn($element->line, "Attribute \"{$attribute}\" of <{$element->name}> needs a value");
			return null;
		}
		return $value;
	}

	private function string(RawElement $element, string $attribute, string $default): string {
		$value = $this->optionalString($element, $attribute);
		if ($value === null && !$element->has($attribute)) {
			$this->warn($element->line, "<{$element->name}> needs the attribute \"{$attribute}\"");
		}
		return $value ?? $default;
	}

	private function int(RawElement $element, string $attribute): ?int {
		$value = $this->optionalString($element, $attribute);
		if ($value === null) {
			return null;
		}
		if (preg_match('/^[+-]?\d+$/', trim($value)) !== 1) {
			$this->warn($element->line, "Attribute \"{$attribute}\" of <{$element->name}> must be a number, not \"{$value}\"");
			return null;
		}
		return (int)$value;
	}

	private function bool(RawElement $element, string $attribute): bool {
		$value = $element->get($attribute);
		if (is_bool($value) || $value === null) {
			return $value === true;
		}
		return match ($value) {
			'false' => false,
			'true' => true,
			default => $this->invalidBool($element, $attribute, $value),
		};
	}

	private function invalidBool(RawElement $element, string $attribute, string $value): bool {
		$this->warn($element->line, "Attribute \"{$attribute}\" of <{$element->name}> must be true or false, not \"{$value}\"");
		return true;
	}

	private function checkAttributes(RawElement $element): void {
		foreach ($element->unusedAttributes() as $attribute) {
			$this->warn($element->line, "Unknown attribute \"{$attribute}\" on <{$element->name}> ignored");
		}
	}

	// ------------------------------------------------------------------

	/**
	 * Match a regular expression
	 *
	 * @return ?array<int,string> The groups, or null if it does not match
	 */
	private static function match(string $pattern, string $subject): ?array {
		return preg_match($pattern, $subject, $m) === 1 ? $m : null;
	}

	private function warn(int $line, string $message): void {
		$this->logger->warning("AoXML line {$line}: {$message}", ['line' => $line]);
	}
}
