<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use function Safe\preg_match;

use DateTimeImmutable;
use DateTimeInterface;

/** Points in time as AoXML attribute values: ISO 8601 with time zone */
final class Iso8601 {
	/** Date, time with optional seconds, and "Z" or an offset (±hh:mm or ±hhmm) */
	private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})$/';

	/** Parse e.g. "2026-11-20T14:02:00Z"; null if invalid or without a time zone */
	public static function parse(string $value): ?DateTimeImmutable {
		if (preg_match(self::PATTERN, $value, $matches) !== 1) {
			return null;
		}
		$format = ($matches[1] ?? '') === '' ? '!Y-m-d\TH:iP' : '!Y-m-d\TH:i:sP';
		$result = DateTimeImmutable::createFromFormat($format, $value);
		$errors = DateTimeImmutable::getLastErrors();
		if ($result === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
			return null;
		}
		return $result;
	}

	/** The canonical form: Y-m-d\TH:i:s and "Z" for UTC, else ±hh:mm */
	public static function format(DateTimeInterface $value): string {
		$offset = $value->getOffset();
		if ($offset === 0) {
			return $value->format('Y-m-d\TH:i:s') . 'Z';
		}
		return $value->format('Y-m-d\TH:i:sP');
	}
}
