<?php declare(strict_types=1);

namespace AO\Markup\Export\Html;

/** HTML escaping */
final class Html {
	/** Escape text and attribute values; quotes as &quot; and &#39; */
	public static function escape(string $text): string {
		return str_replace('&apos;', '&#39;', htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8'));
	}

	/** A number as short as possible: 73.0 → "73" */
	public static function number(float $value): string {
		return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
	}
}
