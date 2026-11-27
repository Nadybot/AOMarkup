<?php declare(strict_types=1);

namespace AO\Markup\Builder;

use function Safe\{preg_match_all, preg_split};

use AO\Markup\Content;
use AO\Markup\Node\{LineBreak, Node, Paragraph, PopupLink, Text};
use AO\Markup\Parser\{AoXmlParser, TokenType, Tokenizer};
use AO\Markup\Transform\Tree;
use AO\Markup\Widget\WidgetRegistry;
use Psr\Log\{AbstractLogger, LoggerInterface, NullLogger};
use Stringable;

/**
 * Parsed AoXML with {name} slots, for B::format() and B::aoxml()
 *
 * The format is parsed once (and cached); arguments are put into the tree in place
 * of their slots and never parsed. See docs/Builder.md.
 *
 * @internal
 */
final class Template {
	/** A slot, or "{{" for a literal "{" */
	private const SLOT = '/(\{\{|\{[A-Za-z_][A-Za-z0-9_]*\})/';

	/** Parsed formats kept at most; formats are nearly always constants */
	private const CACHE_SIZE = 1_000;

	/** @var array<string,self> */
	private static array $cache = [];

	/**
	 * @param list<Node>                              $nodes    Inline nodes (format) or blocks (aoxml)
	 * @param array<string,bool>                      $slots    Names of the slots in content; true if
	 *                                                          blocks may go into every one of them
	 * @param list<array{string,string,array<mixed>}> $warnings Level, message and context of the parse warnings
	 */
	private function __construct(
		public readonly string $function,
		private array $nodes,
		private array $slots,
		private array $warnings,
	) {
	}

	/**
	 * The parsed $format; warnings of parsing go to $logger, on every call
	 *
	 * @param bool $inline True for B::format() (inline content), false for B::aoxml() (blocks)
	 */
	public static function for(string $format, bool $inline, LoggerInterface $logger): self {
		$key = ($inline ? 'i:' : 'b:') . $format;
		$template = self::$cache[$key] ?? null;
		if ($template === null) {
			$template = self::parse($format, $inline);
			if (count(self::$cache) >= self::CACHE_SIZE) {
				unset(self::$cache[array_key_first(self::$cache)]);
			}
			self::$cache[$key] = $template;
		}
		foreach ($template->warnings as [$level, $message, $context]) {
			$logger->log($level, $message, $context);
		}
		return $template;
	}

	/**
	 * The arguments that fill slots; warns about the others and about slots without one
	 *
	 * @param array<int|string,mixed> $args
	 *
	 * @return array<string,mixed>
	 */
	public function arguments(array $args, LoggerInterface $logger): array {
		$named = [];
		foreach ($args as $name => $value) {
			if (is_int($name)) {
				$logger->warning("{$this->function}(): positional argument #{$name} ignored, slots are filled by name");
				continue;
			}
			if (!isset($this->slots[$name])) {
				$logger->warning("{$this->function}(): argument \"{$name}\" ignored, there is no slot {{$name}}");
				continue;
			}
			if (!$this->slots[$name] && self::hasBlock($value)) {
				$logger->warning("{$this->function}(): argument \"{$name}\" is a block, dropped where {{$name}} is inside a line");
			}
			$named[$name] = $value;
		}
		foreach (array_keys($this->slots) as $name) {
			if (!array_key_exists($name, $named)) {
				$logger->warning("{$this->function}(): no argument for slot {{$name}}");
			}
		}
		return $named;
	}

	/**
	 * The nodes with the slots filled
	 *
	 * @param array<string,mixed> $args As returned by arguments()
	 *
	 * @return list<Node>
	 */
	public function fill(array $args, BuildContext $context): array {
		$result = [];
		foreach ($this->nodes as $node) {
			array_push($result, ...$this->node($node, true, $args, $context));
		}
		return $result;
	}

	/** Does $value give a block? */
	private static function hasBlock(mixed $value): bool {
		if (!self::isContent($value)) {
			return false;
		}
		foreach (ContentConverter::inline([$value], new BuildContext()) as $node) {
			if (ContentConverter::isBlock($node)) {
				return true;
			}
		}
		return false;
	}

	/** @phpstan-assert-if-true null|string|int|float|Stringable|Content $value */
	private static function isContent(mixed $value): bool {
		return $value === null || is_string($value) || is_int($value) || is_float($value)
			|| $value instanceof Stringable || $value instanceof Content;
	}

	private static function parse(string $format, bool $inline): self {
		$function = $inline ? 'B::format' : 'B::aoxml';
		$log = new class ($function) extends AbstractLogger {
			/** @var list<array{string,string,array<mixed>}> */
			public array $records = [];

			public function __construct(private string $function) {
			}

			/** @param array<mixed> $context */
			public function log($level, string|Stringable $message, array $context=[]): void {
				$this->records []= [(string)$level, "{$this->function}(): {$message}", $context];
			}
		};

		self::checkAttributes($format, $log);
		$document = (new AoXmlParser(WidgetRegistry::withBuiltins(), $log))->parse($format);
		if (count($document->windows) > 0) {
			$log->warning('<window> is not allowed here, build windows with B::window()');
		}
		$blocks = [];
		foreach ($document->body->children as $block) {
			array_push($blocks, ...self::withoutPopups($block, $log));
		}
		$nodes = $inline ? self::inline($blocks, $log) : $blocks;

		$slots = [];
		foreach ($nodes as $node) {
			self::collectSlots($node, $inline, $slots);
		}
		return new self($function, $nodes, $slots, $log->records);
	}

	/** Slots in attribute values are not filled */
	private static function checkAttributes(string $format, LoggerInterface $log): void {
		foreach ((new Tokenizer(new NullLogger()))->tokenize($format) as $token) {
			if ($token->type !== TokenType::Open) {
				continue;
			}
			foreach ($token->attributes as $attribute => $value) {
				if (!is_string($value) || preg_match_all(self::SLOT, $value, $m) === 0) {
					continue;
				}
				foreach ($m[1] as $slot) {
					if ($slot !== '{{') {
						$log->warning(
							"AoXML line {$token->line}: {$slot} in attribute {$attribute} of <{$token->name}> is not filled, "
							. 'slots are only allowed in content; pass the element as an argument instead',
							['line' => $token->line]
						);
					}
				}
			}
		}
	}

	/**
	 * $node with every <popup> replaced by its content
	 *
	 * @return list<Node>
	 */
	private static function withoutPopups(Node $node, LoggerInterface $log): array {
		if ($node instanceof PopupLink) {
			$log->warning('<popup> is not allowed here, pass $window->link() as an argument');
			$children = [];
			foreach ($node->children as $child) {
				array_push($children, ...self::withoutPopups($child, $log));
			}
			return $children;
		}
		return [Tree::flatMapChildren($node, static fn (Node $child): array => self::withoutPopups($child, $log))];
	}

	/**
	 * The inline content of $blocks: paragraphs are joined by an empty line, other blocks dropped
	 *
	 * @param list<Node> $blocks
	 *
	 * @return list<Node>
	 */
	private static function inline(array $blocks, LoggerInterface $log): array {
		$nodes = [];
		foreach ($blocks as $block) {
			if (!$block instanceof Paragraph) {
				$name = (new \ReflectionClass($block))->getShortName();
				$log->warning("blocks are not allowed in B::format(), use B::aoxml(); {$name} dropped");
				continue;
			}
			if (count($nodes) > 0) {
				array_push($nodes, new LineBreak(), new LineBreak());
			}
			array_push($nodes, ...$block->children);
		}
		return $nodes;
	}

	/**
	 * @param bool               $blocks True if the node is in a place where blocks may go
	 * @param array<string,bool> $slots
	 */
	private static function collectSlots(Node $node, bool $blocks, array &$slots): void {
		if ($node instanceof Text) {
			preg_match_all(self::SLOT, $node->text, $m);
			foreach ($m[1] as $slot) {
				if ($slot !== '{{') {
					$name = substr($slot, 1, -1);
					$slots[$name] = ($slots[$name] ?? true) && $blocks;
				}
			}
			return;
		}
		$paragraph = $node instanceof Paragraph;
		Tree::mapChildren($node, static function (Node $child) use ($paragraph, &$slots): Node {
			self::collectSlots($child, $paragraph, $slots);
			return $child;
		});
	}

	/**
	 * $node with its slots filled
	 *
	 * @param bool                $blocks True if the node is in a place where blocks may go
	 *                                    (directly in a paragraph, or at the top)
	 * @param array<string,mixed> $args
	 *
	 * @return list<Node>
	 */
	private function node(Node $node, bool $blocks, array $args, BuildContext $context): array {
		if ($node instanceof Text) {
			return $this->text($node, $blocks, $args, $context);
		}
		if ($node instanceof Paragraph) {
			$children = [];
			foreach ($node->children as $child) {
				array_push($children, ...$this->node($child, true, $args, $context));
			}
			if ($children === $node->children) {
				return [$node];
			}
			$blocks = ContentConverter::flow($children, $context);
			if (count($blocks) === 1) {
				return $blocks;
			}
			$blocks = array_map(self::trim(...), $blocks);
			return array_values(array_filter($blocks, static fn (Node $b): bool => !$b instanceof Paragraph || count($b->children) > 0));
		}
		return [Tree::flatMapChildren(
			$node,
			fn (Node $child): array => $this->node($child, false, $args, $context)
		)];
	}

	/** A paragraph next to a block without the spaces that separated them */
	private static function trim(Node $block): Node {
		if (!$block instanceof Paragraph) {
			return $block;
		}
		$children = $block->children;
		$last = count($children) - 1;
		if ($children[0] instanceof Text) {
			$children[0] = new Text(ltrim($children[0]->text, ' '));
		}
		if ($children[$last] instanceof Text) {
			$children[$last] = new Text(rtrim($children[$last]->text, ' '));
		}
		$children = array_values(array_filter($children, static fn (Node $n): bool => !$n instanceof Text || $n->text !== ''));
		return new Paragraph(...$children);
	}

	/**
	 * A text with its slots replaced by their arguments
	 *
	 * @param array<string,mixed> $args
	 *
	 * @return list<Node>
	 */
	private function text(Text $text, bool $blocks, array $args, BuildContext $context): array {
		if (!str_contains($text->text, '{')) {
			return [$text];
		}
		$pieces = [];
		foreach (preg_split(self::SLOT, $text->text, -1, \PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
			if ($i % 2 === 0 || $part === '{{') {
				$pieces []= $i % 2 === 0 ? $part : '{';
				continue;
			}
			$value = $args[substr($part, 1, -1)] ?? null;
			if (self::isContent($value)) {
				$pieces []= $value;
			}
		}
		$nodes = ContentConverter::inline($pieces, $context);
		if ($blocks) {
			return $nodes;
		}
		// arguments() has warned already
		return array_values(array_filter($nodes, static fn (Node $node): bool => !ContentConverter::isBlock($node)));
	}
}
