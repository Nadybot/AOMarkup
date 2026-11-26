<?php declare(strict_types=1);

namespace AO\Markup\Export\Discord;

/** The size limits of a Discord message, in characters */
final class Limits {
	public const CONTENT = 2_000;
	public const EMBEDS = 10;
	public const TITLE = 256;
	public const DESCRIPTION = 4_096;
	public const FIELDS = 25;
	public const FIELD_NAME = 256;
	public const FIELD_VALUE = 1_024;
	public const FOOTER = 2_048;

	/** All titles, descriptions, field names and values and footers of a message together */
	public const TOTAL = 6_000;

	/**
	 * Is a message within all limits?
	 *
	 * @param array<string,mixed> $message
	 */
	public static function fits(array $message): bool {
		if (self::length($message['content'] ?? '') > self::CONTENT) {
			return false;
		}
		$embeds = is_array($message['embeds'] ?? null) ? $message['embeds'] : [];
		if (count($embeds) > self::EMBEDS) {
			return false;
		}
		$total = 0;
		foreach ($embeds as $embed) {
			if (!is_array($embed)) {
				continue;
			}
			$parts = [
				[$embed['title'] ?? '', self::TITLE],
				[$embed['description'] ?? '', self::DESCRIPTION],
				[is_array($embed['footer'] ?? null) ? ($embed['footer']['text'] ?? '') : '', self::FOOTER],
			];
			$fields = is_array($embed['fields'] ?? null) ? $embed['fields'] : [];
			if (count($fields) > self::FIELDS) {
				return false;
			}
			foreach ($fields as $field) {
				if (is_array($field)) {
					$parts []= [$field['name'] ?? '', self::FIELD_NAME];
					$parts []= [$field['value'] ?? '', self::FIELD_VALUE];
				}
			}
			foreach ($parts as [$text, $max]) {
				$length = self::length($text);
				if ($length > $max) {
					return false;
				}
				$total += $length;
			}
		}
		return $total <= self::TOTAL;
	}

	private static function length(mixed $text): int {
		return is_string($text) ? mb_strlen($text) : 0;
	}
}
