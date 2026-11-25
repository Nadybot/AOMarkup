<?php declare(strict_types=1);

namespace AO\Markup\Parser;

/**
 * Whitespace state while turning one run of inline content into nodes
 *
 * A run is everything between two block boundaries. Whitespace is not emitted
 * when it is read, but when the next content follows; this is what drops
 * leading and trailing whitespace and collapses runs of spaces, even across
 * element boundaries.
 *
 * @internal
 */
final class FlowState {
	/** Source line breaks seen since the last content */
	public int $newlines = 0;

	/** A space is due before the next content */
	public bool $space = false;

	/** Nothing but whitespace since the last line break */
	public bool $lineStart = true;

	/** Anything was emitted in this run */
	public bool $hasContent = false;
}
