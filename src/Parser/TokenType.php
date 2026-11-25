<?php declare(strict_types=1);

namespace AO\Markup\Parser;

/** @internal */
enum TokenType {
	/** Decoded text; Token::$text */
	case Text;

	/** An opening or self-closing tag; Token::$text is the raw source of the tag */
	case Open;

	/** A closing tag; Token::$text is the raw source of the tag */
	case Close;
}
