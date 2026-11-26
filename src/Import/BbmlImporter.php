<?php declare(strict_types=1);

namespace AO\Markup\Import;

use function Safe\{preg_match, preg_replace};

use AO\Markup\Context\PlaceholderProvider;
use AO\Markup\{Document, Window};
use AO\Markup\Export\TextContent;
use AO\Markup\Import\Bbml\{BbmlLine, BbmlToken, BbmlTokenizer, InlineStack, TableDetector};
use AO\Markup\Node\{
	Center,
	CharLink,
	ClientCommandLink,
	Color,
	CommandChannel,
	CommandDisplay,
	CommandLink,
	Container,
	Heading,
	Image,
	ImageSource,
	Indent,
	ItemLink,
	LineBreak,
	NanoLink,
	Node,
	PageBreak,
	Paragraph,
	Placeholder,
	PopupLink,
	Repeat,
	SkillLink,
	Style,
	StyleName,
	Text,
	UrlLink,
	WaypointLink,
};
use AO\Markup\Node\Fragment;
use Psr\Log\{LoggerInterface, NullLogger};

/**
 * Reads BBML, the Budabot markup language (<highlight>…<end>, <tab>, text:// popups, …)
 *
 * Funcom AOML is a subset of BBML, so plain AOML is read as well. The translation is
 * literal, but clean, and never guesses: <font color> becomes a Color node (colours are
 * never turned back into semantic styles), aligned text stays text (no tables, no
 * sections, no footers), and nothing becomes a widget. The rules are in docs/AoXML.md §6.
 */
final class BbmlImporter implements Importer {
	/** BBML colour tags and the styles they become */
	private const STYLES = [
		'highlight' => StyleName::Strong,
		'header' => StyleName::Header,
		'header2' => StyleName::Header2,
		'on' => StyleName::On,
		'off' => StyleName::Off,
		'neutral' => StyleName::Neutral,
		'omni' => StyleName::Omni,
		'clan' => StyleName::Clan,
		'unknown' => StyleName::Unknown,
		'white' => StyleName::White,
		'yellow' => StyleName::Yellow,
		'blue' => StyleName::Blue,
		'green' => StyleName::Green,
		'red' => StyleName::Red,
		'orange' => StyleName::Orange,
		'grey' => StyleName::Grey,
		'cyan' => StyleName::Cyan,
		'violet' => StyleName::Violet,
	];

	private const PLACEHOLDERS = [PlaceholderProvider::MY_NAME, PlaceholderProvider::MY_GUILD, PlaceholderProvider::SYMBOL];

	/** @var array<string,Window> */
	private array $windows = [];

	/**
	 * @param ?string $botName      Name of the bot, to recognise its commands in plain AOML
	 *                              ("/tell Nadybot !help"); BBML uses <myname>
	 * @param ?string $symbol       Command prefix of the bot, for the same purpose; BBML uses <symbol>
	 * @param bool    $detectTables Turn lines with "|" between columns into tables. This guesses, so
	 *                              it is off by default; see docs/AoXML.md §6.1
	 */
	public function __construct(
		private LoggerInterface $logger=new NullLogger(),
		private ?string $botName=null,
		private ?string $symbol=null,
		private bool $detectTables=false,
	) {
	}

	public function import(string $input): Document {
		$this->windows = [];
		$body = $this->blocks((new BbmlTokenizer())->tokenize($input), inWindow: false);
		$windows = $this->windows;
		$this->windows = [];
		return new Document(new Fragment(...$body), $windows);
	}

	/**
	 * The window of a text:// popup; returns its id
	 *
	 * @param list<Node> $label
	 */
	private function window(string $content, array $label): string {
		$tokens = (new BbmlTokenizer())->tokenize(str_replace('&quot;', '"', $content));

		// Text::makeBlob() starts with the window colour and the title in <header>
		if (isset($tokens[0]) && $tokens[0]->isTag('font')) {
			array_shift($tokens);
		}
		$title = trim(TextContent::of(...$label));
		if (count($tokens) >= 3 && $tokens[0]->isTag('header') && $tokens[1]->type === BbmlToken::TEXT && $tokens[2]->isTag('end')) {
			$title = trim($tokens[1]->text);
			$tokens = array_slice($tokens, 3);
			while (isset($tokens[0]) && $tokens[0]->type === BbmlToken::NEWLINE) {
				array_shift($tokens);
			}
		} elseif (self::firstLineText($tokens) === $title) {
			// Plain AOML: the title is the first line, in any colour
			$tokens = self::withoutFirstLine($tokens);
		}
		$id = 'window' . (count($this->windows) + 1);
		$this->windows[$id] = new Window($id, $title);
		$this->windows[$id] = new Window($id, $title, ...$this->blocks($tokens, inWindow: true));
		return $id;
	}

	/**
	 * The text of the first line, if an empty line follows it
	 *
	 * @param list<BbmlToken> $tokens
	 */
	private static function firstLineText(array $tokens): ?string {
		$text = '';
		foreach ($tokens as $i => $token) {
			if ($token->type === BbmlToken::NEWLINE) {
				return ($tokens[$i + 1] ?? null)?->type === BbmlToken::NEWLINE ? trim($text) : null;
			}
			if ($token->type === BbmlToken::TEXT) {
				$text .= $token->text;
			} elseif (!$token->isTag('font', 'end') && !$token->isClosingTag('font')) {
				return null;
			}
		}
		return null;
	}

	/**
	 * The tokens after the first line and the empty lines that follow it
	 *
	 * @param list<BbmlToken> $tokens
	 *
	 * @return list<BbmlToken>
	 */
	private static function withoutFirstLine(array $tokens): array {
		while (count($tokens) > 0 && $tokens[0]->type !== BbmlToken::NEWLINE) {
			array_shift($tokens);
		}
		while (count($tokens) > 0 && $tokens[0]->type === BbmlToken::NEWLINE) {
			array_shift($tokens);
		}
		return $tokens;
	}

	/**
	 * Lines become paragraphs, empty lines separate them, leading <tab>s indent them
	 *
	 * @param list<BbmlToken> $tokens
	 *
	 * @return list<Node>
	 */
	private function blocks(array $tokens, bool $inWindow): array {
		/** @var list<array{list<BbmlLine>,string}> $frames Lines of the open block tags (permheader, center) */
		$frames = [[[], '']];
		$line = [];
		$open = [];
		foreach ($tokens as $token) {
			if ($token->type === BbmlToken::NEWLINE || $token->isTag('br')) {
				$this->finishLine($line, $open, $frames, $inWindow, always: true);
			} elseif ($token->isTag('pagebreak')) {
				$this->finishLine($line, $open, $frames, $inWindow);
				self::addItem($frames, new BbmlLine(0, BbmlLine::BLOCK, new PageBreak()));
			} elseif ($token->isTag('permheader', 'center')) {
				$this->finishLine($line, $open, $frames, $inWindow);
				$frames []= [[], $token->name];
			} elseif ($token->isClosingTag('permheader', 'center') && count($frames) > 1 && $frames[count($frames) - 1][1] === $token->name) {
				$this->finishLine($line, $open, $frames, $inWindow);
				[$items, $name] = array_pop($frames) ?? [[], ''];
				$children = $this->fold($items, 0, $inWindow);
				self::addItem($frames, new BbmlLine(0, BbmlLine::BLOCK, $name === 'center' ? new Center(...$children) : new Repeat(...$children), inner: $items));
			} else {
				$line []= $token;
			}
		}
		$this->finishLine($line, $open, $frames, $inWindow);

		// Block tags that were never closed just end
		$items = [];
		foreach ($frames as $frame) {
			array_push($items, ...$frame[0]);
		}
		return $this->fold($items, 0, $inWindow);
	}

	/**
	 * Add the current line to the innermost open block
	 *
	 * @param list<BbmlToken>                      $line
	 * @param list<array{string,string|StyleName}> $open
	 * @param list<array{list<BbmlLine>,string}>   $frames
	 * @param bool                                 $always Also add an empty line (it separates paragraphs)
	 */
	private function finishLine(array &$line, array &$open, array &$frames, bool $inWindow, bool $always=false): void {
		if (count($line) > 0 || $always) {
			self::addItem($frames, $this->line($line, $open, $inWindow));
		}
		$line = [];
	}

	/**
	 * @param list<array{list<BbmlLine>,string}> $frames
	 *
	 * @param-out list<array{list<BbmlLine>,string}> $frames
	 */
	private static function addItem(array &$frames, BbmlLine $item): void {
		[$items, $name] = array_pop($frames) ?? [[], ''];
		$items []= $item;
		$frames []= [$items, $name];
	}

	/**
	 * One line: its indentation level, its kind (line, heading, blank) and its content
	 *
	 * @param list<BbmlToken>                      $tokens
	 * @param list<array{string,string|StyleName}> $open   Inline tags still open from the previous line
	 */
	private function line(array $tokens, array &$open, bool $inWindow): BbmlLine {
		// Leading <tab>s are the indentation, and so are 4 spaces each (what Nadybot makes of a <tab>)
		$level = 0;
		$spaces = 0;
		while (count($tokens) > 0) {
			if ($tokens[0]->isTag('tab')) {
				$level++;
				array_shift($tokens);
				continue;
			}
			if ($tokens[0]->type !== BbmlToken::TEXT) {
				break;
			}
			$text = $tokens[0]->text;
			$stripped = ltrim($text, ' ');
			$spaces += strlen($text) - strlen($stripped);
			if ($stripped !== '') {
				$tokens[0] = new BbmlToken(BbmlToken::TEXT, $stripped);
				break;
			}
			array_shift($tokens);
		}
		$level += intdiv($spaces, 4);

		// A line that is only <header>…<end> or <header2>…<end> is a heading
		if (count($open) === 0 && count($tokens) >= 3 && $tokens[0]->isTag('header', 'header2') && $tokens[count($tokens) - 1]->isTag('end')) {
			$inner = array_slice($tokens, 1, -1);
			if (count(array_filter($inner, static fn (BbmlToken $t): bool => $t->isTag('end', 'font') || $t->isClosingTag('font'))) === 0) {
				$children = $this->inline($inner, $open, $inWindow);
				return new BbmlLine($level, BbmlLine::HEADING, new Heading($tokens[0]->name === 'header' ? 1 : 2, ...$children), $tokens);
			}
		}

		$nodes = $this->inline($tokens, $open, $inWindow);
		if (count($nodes) === 0) {
			return new BbmlLine($level, BbmlLine::BLANK, new Fragment());
		}
		return new BbmlLine($level, BbmlLine::LINE, new Fragment(...$nodes), $tokens);
	}

	/**
	 * Build blocks from lines: consecutive lines on the same level form a paragraph,
	 * lines on a deeper level go into an <indent>
	 *
	 * @param list<BbmlLine> $items
	 *
	 * @return list<Node>
	 */
	private function fold(array $items, int $level=0, bool $inWindow=true): array {
		if ($this->detectTables) {
			$items = (new TableDetector(function (array $tokens) use ($inWindow): array {
				$open = [];
				return $this->inline($tokens, $open, $inWindow);
			}))->detect($items, $level);
		}
		$blocks = [];
		$paragraph = [];
		$count = count($items);
		for ($i = 0; $i < $count; $i++) {
			$item = $items[$i];
			[$itemLevel, $kind, $node] = [$item->level, $item->kind, $item->node];
			if ($kind === BbmlLine::BLANK) {
				self::flushParagraph($paragraph, $blocks);
				continue;
			}
			if ($itemLevel > $level && $kind !== BbmlLine::BLOCK) {
				self::flushParagraph($paragraph, $blocks);
				$deeper = [];
				while ($i < $count && $items[$i]->kind !== BbmlLine::BLOCK && ($items[$i]->level > $level || $items[$i]->kind === BbmlLine::BLANK)) {
					$deeper []= $items[$i++];
				}
				// Empty lines after the indented lines belong to this level again
				while (count($deeper) > 0 && $deeper[count($deeper) - 1]->kind === BbmlLine::BLANK) {
					array_pop($deeper);
					$i--;
				}
				$i--;
				$blocks []= new Indent(...$this->fold($deeper, $level + 1, $inWindow));
				continue;
			}
			if ($kind === BbmlLine::LINE) {
				$paragraph []= $node;
				continue;
			}
			self::flushParagraph($paragraph, $blocks);
			$blocks []= $node;
		}
		self::flushParagraph($paragraph, $blocks);
		return $blocks;
	}

	/**
	 * Lines collected so far become a paragraph
	 *
	 * @param list<Node> $paragraph
	 * @param list<Node> $blocks
	 */
	private static function flushParagraph(array &$paragraph, array &$blocks): void {
		if (count($paragraph) === 0) {
			return;
		}
		$nodes = [];
		foreach ($paragraph as $i => $line) {
			if ($i > 0) {
				$nodes []= new LineBreak();
			}
			array_push($nodes, ...$line instanceof Container ? $line->children : [$line]);
		}
		$blocks []= new Paragraph(...$nodes);
		$paragraph = [];
	}

	/**
	 * Inline content of one line
	 *
	 * Tags that are still open at the end of the line are closed there and opened again
	 * on the next line ($open), so styles may span lines.
	 *
	 * @param list<BbmlToken>                      $tokens
	 * @param list<array{string,string|StyleName}> $open
	 *
	 * @return list<Node>
	 */
	private function inline(array $tokens, array &$open, bool $inWindow): array {
		$stack = new InlineStack($open, fn (array $tag, array $children): ?Node => $this->element($tag, $children, $inWindow));
		foreach ($tokens as $token) {
			if ($token->type === BbmlToken::TEXT) {
				$stack->append(new Text($token->text));
				continue;
			}
			$name = $token->name;
			if ($token->closing) {
				$closed = match ($name) {
					'font' => $stack->closeLast('style', 'color', 'black'),
					'a' => $stack->closeLast('link'),
					'u', 'i' => $stack->closeLast($name),
					default => true,
				};
				if (!$closed) {
					$this->logger->warning('BBML: </{name}> without an opening tag', ['name' => $name]);
				}
				continue;
			}
			if ($name === 'end') {
				if (!$stack->closeLast('style', 'color', 'black')) {
					$this->logger->warning('BBML: <end> without an opening tag');
				}
			} elseif (isset(self::STYLES[$name])) {
				$stack->open(['style', self::STYLES[$name]]);
			} elseif ($name === 'black' || $name === 'u' || $name === 'i') {
				$stack->open([$name, '']);
			} elseif ($name === 'font') {
				$stack->open(['color', self::color($token->attributes['color'] ?? '')]);
			} elseif ($name === 'a') {
				$stack->open(['link', $token->attributes['href'] ?? '']);
			} elseif (in_array($name, self::PLACEHOLDERS, true)) {
				$stack->append(new Placeholder($name));
			} elseif ($name === 'tab') {
				$stack->append(new Text(' '));
			} elseif ($name === 'img') {
				$image = $this->image($token->attributes['src'] ?? '');
				if ($image !== null) {
					$stack->append($image);
				}
			} else {
				$this->logger->warning('BBML: unknown tag <{name}>', ['name' => $name]);
			}
		}
		[$nodes, $open] = $stack->finish();
		return self::clean($nodes);
	}

	/**
	 * The node for a closed inline tag
	 *
	 * @param array{string,string|StyleName} $tag
	 * @param list<Node>                     $children
	 */
	private function element(array $tag, array $children, bool $inWindow): ?Node {
		[$kind, $value] = $tag;
		if (count($children) === 0 && $kind !== 'link') {
			return null;
		}
		return match ($kind) {
			'style' => $value instanceof StyleName ? new Style($value, ...$children) : null,
			// Black text is invisible padding (black zeros), like <black>
			'color' => match (true) {
				$value === '#000000' => null,
				is_string($value) && $value !== '' => new Color($value, ...$children),
				default => new Fragment(...$children),
			},
			'black' => null,
			'u' => new Style(StyleName::Underline, ...$children),
			'i' => new Style(StyleName::Italic, ...$children),
			'link' => $this->link(is_string($value) ? $value : '', $children, $inWindow),
			default => new Fragment(...$children),
		};
	}

	private function image(string $src): ?Image {
		if (($m = self::matches('#^rdb://(\d+)$#', $src)) !== null) {
			return new Image(ImageSource::Rdb, $m[1]);
		}
		if (($m = self::matches('#^tdb://id:([A-Za-z0-9_]+)$#', $src)) !== null) {
			return new Image(ImageSource::Tdb, $m[1]);
		}
		$this->logger->warning('BBML: unknown image source "{src}"', ['src' => $src]);
		return null;
	}

	/**
	 * The groups of a match, or null
	 *
	 * @return ?array<int,string>
	 */
	private static function matches(string $pattern, string $subject): ?array {
		return preg_match($pattern, $subject, $match) === 1 ? $match : null;
	}

	/** "#RRGGBB" in upper case, or "" if it is no colour */
	private static function color(string $value): string {
		$value = trim($value, "'\" ");
		return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : '';
	}

	/**
	 * The node for <a href>, by its target
	 *
	 * @param list<Node> $children
	 */
	private function link(string $href, array $children, bool $inWindow): Node {
		if (str_starts_with($href, 'text://')) {
			if ($inWindow) {
				$this->logger->warning('BBML: popups inside popups are not supported, only the link text is kept');
				return new Fragment(...$children);
			}
			return new PopupLink($this->window(substr($href, 7), $children), ...$children);
		}
		$href = BbmlTokenizer::decode($href);
		$label = self::signature($children);
		$text = trim(TextContent::of(...$children));
		return match (true) {
			($m = self::matches('#^itemref://(\d+)/(\d+)/(\d+)$#', $href)) !== null => new ItemLink((int)$m[1], (int)$m[2], (int)$m[3], ...$children),
			($m = self::matches('#^itemid://53019/(\d+)$#', $href)) !== null => new NanoLink((int)$m[1], ...$children),
			($m = self::matches('#^skillid://(\d+)$#', $href)) !== null => new SkillLink((int)$m[1], ...$children),
			($m = self::matches('#^user://(.+)$#', $href)) !== null => new CharLink($m[1], ...($text === $m[1] ? [] : $children)),
			self::matches('#^https?://#', $href) !== null => new UrlLink($href, ...($text === $href ? [] : $children)),
			str_starts_with($href, 'chatcmd://') => $this->chatCommand(substr($href, 10), $children, $label, $text),
			default => $this->unknownLink($href, $children),
		};
	}

	/**
	 * A chatcmd:// link: a bot command, a URL, a waypoint or a client command
	 *
	 * @param list<Node> $children
	 */
	private function chatCommand(string $command, array $children, string $label, string $text): Node {
		[$command, $label] = $this->withPlaceholders($command, $label);
		if (($m = self::matches('#^/tell <myname> (?:<symbol>)?(.+)$#', $command)) !== null) {
			return self::botCommand($m[1], CommandChannel::Tell, "/tell <myname> <symbol>{$m[1]}", $children, $label);
		}
		if (($m = self::matches('#^/g <myname> (?:<symbol>)?(.+)$#', $command)) !== null) {
			return self::botCommand($m[1], CommandChannel::Priv, "/g <myname> <symbol>{$m[1]}", $children, $label);
		}
		if (($m = self::matches('#^/o <symbol>(.+)$#', $command)) !== null) {
			return self::botCommand($m[1], CommandChannel::Org, "/o <symbol>{$m[1]}", $children, $label);
		}
		if (($m = self::matches('#^/start (\S+)$#', $command)) !== null) {
			return new UrlLink($m[1], ...($text === $m[1] ? [] : $children));
		}
		if (($m = self::matches('#^/waypoint (-?\d+) (-?\d+) (\d+)$#', $command)) !== null) {
			return new WaypointLink((int)$m[1], (int)$m[2], (int)$m[3], ...$children);
		}
		if (str_contains($command, '<')) {
			$this->logger->warning('BBML: client command with placeholders kept as text: {command}', ['command' => $command]);
		}
		return new ClientCommandLink($command, ...($text === $command ? [] : $children));
	}

	/**
	 * Plain AOML has the bot's name and command prefix instead of <myname> and <symbol>
	 *
	 * @return array{string,string} The command and the link text
	 */
	private function withPlaceholders(string $command, string $label): array {
		return [$this->placeholdersIn($command), $this->placeholdersIn($label)];
	}

	/** "/tell Nadybot !help" becomes "/tell <myname> <symbol>help", if bot name and symbol are known */
	private function placeholdersIn(string $text): string {
		if (isset($this->botName)) {
			foreach (['/tell', '/g'] as $channel) {
				$prefix = "{$channel} {$this->botName} ";
				if (str_starts_with($text, $prefix)) {
					$text = "{$channel} <myname> " . substr($text, strlen($prefix));
				}
			}
		}
		if (isset($this->symbol) && $this->symbol !== '') {
			$text = preg_replace('#^((?:/tell|/g) <myname> |/o )?' . preg_quote($this->symbol, '#') . '#', '$1<symbol>', $text);
		}
		return $text;
	}

	/**
	 * A bot command; the link text is left out if it is what <cmd> would show anyway
	 *
	 * @param list<Node> $children
	 */
	private static function botCommand(string $command, CommandChannel $via, string $asTyped, array $children, string $label): CommandLink {
		return match ($label) {
			"<symbol>{$command}" => new CommandLink($command, CommandDisplay::Command, $via),
			$asTyped => new CommandLink($command, CommandDisplay::Tell, $via),
			default => new CommandLink($command, CommandDisplay::Command, $via, ...$children),
		};
	}

	/** @param list<Node> $children */
	private function unknownLink(string $href, array $children): Node {
		$this->logger->warning('BBML: unknown link target "{href}", only the link text is kept', ['href' => $href]);
		return new Fragment(...$children);
	}

	/**
	 * The link text with placeholders as "<name>", to compare it with a command
	 *
	 * @param list<Node> $nodes
	 */
	private static function signature(array $nodes): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= match (true) {
				$node instanceof Text => $node->text,
				$node instanceof Placeholder => "<{$node->name}>",
				$node instanceof Container => self::signature($node->children),
				default => '',
			};
		}
		return trim(preg_replace('/\s+/', ' ', $result));
	}

	/**
	 * Collapse whitespace, trim the line, move spaces at the edges of styles outside,
	 * unwrap fragments and merge adjacent texts
	 *
	 * @param list<Node> $nodes
	 *
	 * @return list<Node>
	 */
	private static function clean(array $nodes): array {
		$flat = self::normalise($nodes);
		// Trim the line
		while (count($flat) > 0 && $flat[0] instanceof Text && ltrim($flat[0]->text) === '') {
			array_shift($flat);
		}
		if (count($flat) > 0 && $flat[0] instanceof Text) {
			$flat[0] = new Text(ltrim($flat[0]->text));
		}
		while (count($flat) > 0 && $flat[count($flat) - 1] instanceof Text && rtrim($flat[count($flat) - 1]->text) === '') {
			array_pop($flat);
		}
		if (count($flat) > 0 && ($last = $flat[count($flat) - 1]) instanceof Text) {
			$flat[count($flat) - 1] = new Text(rtrim($last->text));
		}
		return $flat;
	}

	/**
	 * @param list<Node> $nodes
	 *
	 * @return list<Node>
	 */
	private static function normalise(array $nodes): array {
		$result = [];
		$push = static function (Node $node) use (&$result): void {
			$last = count($result) > 0 ? $result[count($result) - 1] : null;
			if ($node instanceof Text && $last instanceof Text) {
				$result[count($result) - 1] = new Text(preg_replace('/ {2,}/', ' ', $last->text . $node->text));
				return;
			}
			if ($node instanceof Text && $node->text === '') {
				return;
			}
			$result []= $node;
		};
		foreach ($nodes as $node) {
			if ($node instanceof Fragment) {
				foreach (self::normalise($node->children) as $child) {
					$push($child);
				}
				continue;
			}
			if ($node instanceof Text) {
				$push(new Text(preg_replace('/[ \t]+/', ' ', $node->text)));
				continue;
			}
			if (!$node instanceof Container) {
				$push($node);
				continue;
			}
			// Spaces at the edges of styles and link texts belong outside of them
			$children = self::normalise($node->children);
			$before = $after = '';
			if (count($children) > 0 && $children[0] instanceof Text && $children[0]->text !== ltrim($children[0]->text)) {
				$before = ' ';
				$children[0] = new Text(ltrim($children[0]->text));
			}
			$lastIndex = count($children) - 1;
			if ($lastIndex >= 0 && $children[$lastIndex] instanceof Text && $children[$lastIndex]->text !== rtrim($children[$lastIndex]->text)) {
				$after = ' ';
				$children[$lastIndex] = new Text(rtrim($children[$lastIndex]->text));
			}
			$children = array_values(array_filter($children, static fn (Node $c): bool => !$c instanceof Text || $c->text !== ''));
			$push(new Text($before));
			if (count($children) > 0 || (!$node instanceof Style && !$node instanceof Color)) {
				$push(self::rebuild($node, $children));
			}
			$push(new Text($after));
		}
		return $result;
	}

	/**
	 * $node with new children (links)
	 *
	 * @param list<Node> $children
	 */
	private static function rebuild(Node $node, array $children): Node {
		return match (true) {
			$node instanceof Style => new Style($node->style, ...$children),
			$node instanceof Color => new Color($node->hex, ...$children),
			$node instanceof ItemLink => new ItemLink($node->lowId, $node->highId, $node->ql, ...$children),
			$node instanceof NanoLink => new NanoLink($node->id, ...$children),
			$node instanceof SkillLink => new SkillLink($node->id, ...$children),
			$node instanceof CharLink => new CharLink($node->name, ...$children),
			$node instanceof CommandLink => new CommandLink($node->command, $node->show, $node->via, ...$children),
			$node instanceof ClientCommandLink => new ClientCommandLink($node->command, ...$children),
			$node instanceof UrlLink => new UrlLink($node->href, ...$children),
			$node instanceof WaypointLink => new WaypointLink($node->x, $node->y, $node->playfield, ...$children),
			$node instanceof PopupLink => new PopupLink($node->window, ...$children),
			default => $node,
		};
	}
}
