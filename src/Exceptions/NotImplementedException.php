<?php declare(strict_types=1);

namespace AO\Markup\Exceptions;

/** Thrown by parts of the library that are only specified, not implemented yet */
class NotImplementedException extends \LogicException {
	public static function for(string $what): self {
		return new self("{$what} is not implemented yet");
	}
}
