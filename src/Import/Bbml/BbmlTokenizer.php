<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

use function Safe\{preg_match, preg_match_all};

/**
 * Splits BBML into text, line breaks and tags
 *
 * Attribute values may be unquoted (<font color=#FFFFFF>) or quoted and contain
 * "<" and ">" (href='chatcmd:///tell <myname> help'). A "<" that starts no tag is text.
 *
 * @internal
 */
final class BbmlTokenizer {
	private const TAG = '/\G<(\/?)([A-Za-z][A-Za-z0-9]*)((?:\s+[A-Za-z-]+(?:\s*=\s*(?:\'[^\']*\'|"[^"]*"|[^\s>\'"]+))?)*)\s*\/?>/';
	private const ATTRIBUTE = '/([A-Za-z-]+)(?:\s*=\s*(?:\'([^\']*)\'|"([^"]*)"|([^\s>\'"]+)))?/';

	/** @return list<BbmlToken> */
	public function tokenize(string $input): array {
		$input = str_replace(["\r\n", "\r"], "\n", $input);
		$tokens = [];
		$text = '';
		$pos = 0;
		$length = strlen($input);
		while ($pos < $length) {
			$char = $input[$pos];
			if ($char === "\n") {
				self::flush($text, $tokens);
				$tokens []= new BbmlToken(BbmlToken::NEWLINE);
				$pos++;
				continue;
			}
			if ($char === '<' && preg_match(self::TAG, $input, $match, 0, $pos) === 1) {
				self::flush($text, $tokens);
				$tokens []= new BbmlToken(
					BbmlToken::TAG,
					name: strtolower($match[2] ?? ''),
					closing: ($match[1] ?? '') === '/',
					attributes: self::attributes($match[3] ?? ''),
				);
				$pos += strlen($match[0] ?? '<');
				continue;
			}
			$next = strcspn($input, "<\n", $pos + 1) + $pos + 1;
			$text .= substr($input, $pos, $next - $pos);
			$pos = $next;
		}
		self::flush($text, $tokens);
		return $tokens;
	}

	/** Decode the entities of text or of a link target */
	public static function decode(string $text): string {
		return html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
	}

	/**
	 * Add the collected text as a token
	 *
	 * @param list<BbmlToken> $tokens
	 */
	private static function flush(string &$text, array &$tokens): void {
		if ($text !== '') {
			$tokens []= new BbmlToken(BbmlToken::TEXT, self::decode($text));
			$text = '';
		}
	}

	/** @return array<string,string> */
	private static function attributes(string $source): array {
		$attributes = [];
		preg_match_all(self::ATTRIBUTE, $source, $matches, \PREG_SET_ORDER);
		foreach ($matches as $match) {
			$attributes[strtolower($match[1])] = ($match[2] ?? '') . ($match[3] ?? '') . ($match[4] ?? '');
		}
		return $attributes;
	}
}
