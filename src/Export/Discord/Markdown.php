<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

use function Safe\preg_replace;

/** Discord markdown helpers */
final class Markdown {
	/** Escape characters that Discord would read as markdown; "_" inside a word is literal */
	public static function escape(string $text): string {
		return preg_replace('/(?<![\\p{L}\\p{N}])_|_(?![\\p{L}\\p{N}])/u', '\\\\_', addcslashes($text, '\\*~`|'));
	}

	/** Inline code; uses double backticks if the code contains one */
	public static function code(string $code): string {
		if ($code === '') {
			return '';
		}
		return str_contains($code, '`') ? "`` {$code} ``" : "`{$code}`";
	}

	/** A code block for monospace layout */
	public static function codeBlock(string $text): string {
		return "```\n" . rtrim(str_replace('```', "`\u{200B}``", $text), "\n") . "\n```";
	}

	/**
	 * Split text into chunks of at most $max characters, at line breaks where possible
	 *
	 * @return list<string>
	 */
	public static function chunks(string $text, int $max): array {
		$chunks = [];
		$current = '';
		foreach (explode("\n", $text) as $line) {
			while (mb_strlen($line) > $max) {
				if ($current !== '') {
					$chunks []= $current;
					$current = '';
				}
				$chunks []= mb_substr($line, 0, $max);
				$line = mb_substr($line, $max);
			}
			$candidate = $current === '' ? $line : "{$current}\n{$line}";
			if (mb_strlen($candidate) > $max) {
				$chunks []= $current;
				$candidate = $line;
			}
			$current = $candidate;
		}
		$chunks []= $current;
		return $chunks;
	}
}
