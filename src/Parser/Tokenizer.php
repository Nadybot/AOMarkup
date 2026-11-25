<?php declare(strict_types=1);

namespace AO\Markup\Parser;

use function Safe\{mb_chr, preg_match, preg_match_all, preg_replace_callback};

use Psr\Log\LoggerInterface;
use Safe\Exceptions\MbstringException;

/**
 * Splits AoXML into text and tag tokens
 *
 * Never fails: anything that is not a well-formed tag is text, with a warning.
 *
 * @internal
 */
final class Tokenizer {
	private const NAME = '[A-Za-z_][A-Za-z0-9_.:-]*';

	private const NAMED_ENTITIES = [
		'lt' => '<',
		'gt' => '>',
		'amp' => '&',
		'quot' => '"',
		'apos' => "'",
	];

	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	/** @return list<Token> */
	public function tokenize(string $input): array {
		$input = str_replace(["\r\n", "\r"], "\n", $input);
		$tokens = [];
		$text = '';
		$textLine = 1;
		$line = 1;
		$pos = 0;
		$length = strlen($input);
		while ($pos < $length) {
			$lt = strpos($input, '<', $pos);
			if ($lt === false) {
				$lt = $length;
			}
			if ($lt > $pos) {
				$chunk = substr($input, $pos, $lt - $pos);
				if ($text === '') {
					$textLine = $line;
				}
				$text .= $this->decode($chunk, $line);
				$line += substr_count($chunk, "\n");
				$pos = $lt;
				continue;
			}

			// Comments are dropped
			if (substr_compare($input, '<!--', $pos, 4) === 0) {
				$end = strpos($input, '-->', $pos + 4);
				if ($end === false) {
					$this->warn($line, 'Unterminated comment');
					$end = $length;
				}
				$line += substr_count($input, "\n", $pos, min($end + 3, $length) - $pos);
				$pos = min($end + 3, $length);
				continue;
			}

			$tag = $this->readTag($input, $pos, $line);
			if ($tag === null) {
				$this->warn($line, 'Unescaped "<" taken as text, write it as &lt;');
				if ($text === '') {
					$textLine = $line;
				}
				$text .= '<';
				$pos++;
				continue;
			}
			[$token, $raw] = $tag;
			if ($text !== '') {
				$tokens []= new Token(TokenType::Text, $textLine, $text);
				$text = '';
			}
			$tokens []= $token;
			$line += substr_count($raw, "\n");
			$pos += strlen($raw);
		}
		if ($text !== '') {
			$tokens []= new Token(TokenType::Text, $textLine, $text);
		}
		return $tokens;
	}

	/**
	 * Read the tag starting at $pos, or null if there is no well-formed tag
	 *
	 * @return ?array{Token,string} The token and its raw source
	 */
	private function readTag(string $input, int $pos, int $line): ?array {
		$m = self::matchAt('/\G<\/(' . self::NAME . ')\s*>/A', $input, $pos);
		if ($m !== null) {
			return [new Token(TokenType::Close, $line, $m[0], strtolower($m[1])), $m[0]];
		}
		$attr = '\s+' . self::NAME . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`\/]+))?';
		$m = self::matchAt('/\G<(' . self::NAME . ')((?:' . $attr . ')*)\s*(\/?)>/A', $input, $pos);
		if ($m === null) {
			return null;
		}
		$attributes = $this->parseAttributes($m[2], $line);
		return [new Token(TokenType::Open, $line, $m[0], strtolower($m[1]), $attributes, $m[3] === '/'), $m[0]];
	}

	/** @return array<string,string|true> */
	private function parseAttributes(string $source, int $line): array {
		$attributes = [];
		$pattern = '/\s+(' . self::NAME . ')(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`\/]+)))?/';
		preg_match_all($pattern, $source, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
		foreach ($matches as $m) {
			$name = strtolower((string)$m[1]);
			$value = $m[2] ?? $m[3] ?? null;
			if ($value === null && isset($m[4])) {
				$this->warn($line, "Unquoted value of attribute \"{$name}\"; write {$name}=\"{$m[4]}\"");
				$value = $m[4];
			}
			if (array_key_exists($name, $attributes)) {
				$this->warn($line, "Duplicate attribute \"{$name}\", using the first one");
				continue;
			}
			$attributes[$name] = $value === null ? true : $this->decode($value, $line);
		}
		return $attributes;
	}

	/** Decode entities; a bare "&" stays as it is, with a warning */
	private function decode(string $text, int $line): string {
		if (!str_contains($text, '&')) {
			return $text;
		}
		return preg_replace_callback(
			'/&(?:#([0-9]{1,7})|#x([0-9A-Fa-f]{1,6})|([A-Za-z][A-Za-z0-9]*));|&/',
			function (array $m) use ($line): string {
				if (isset($m[1]) && $m[1] !== '') {
					return $this->codepoint((int)$m[1], $m[0], $line);
				}
				if (isset($m[2]) && $m[2] !== '') {
					return $this->codepoint((int)hexdec($m[2]), $m[0], $line);
				}
				if (isset($m[3]) && $m[3] !== '') {
					if (isset(self::NAMED_ENTITIES[$m[3]])) {
						return self::NAMED_ENTITIES[$m[3]];
					}
					$this->warn($line, "Unknown entity {$m[0]} taken as text");
					return $m[0];
				}
				$this->warn($line, 'Unescaped "&" taken as text, write it as &amp;');
				return '&';
			},
			$text
		);
	}

	private function codepoint(int $codepoint, string $source, int $line): string {
		try {
			return mb_chr($codepoint, 'UTF-8');
		} catch (MbstringException) {
			$this->warn($line, "Invalid character reference {$source} taken as text");
			return $source;
		}
	}

	/**
	 * Match a regular expression anchored at $pos
	 *
	 * @return ?array<int,string> The groups, or null if it does not match
	 */
	private static function matchAt(string $pattern, string $subject, int $pos): ?array {
		return preg_match($pattern, $subject, $m, 0, $pos) === 1 ? $m : null;
	}

	private function warn(int $line, string $message): void {
		$this->logger->warning("AoXML line {$line}: {$message}", ['line' => $line]);
	}
}
