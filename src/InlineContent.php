<?php declare(strict_types=1);

namespace AO\Markup;

/**
 * Content that can stand inside a line: inline nodes, fragments, widgets and inline builders
 *
 * Together with string, int, float and Stringable (text), this is what the builder
 * takes as inline content, see docs/Builder.md.
 */
interface InlineContent extends Content {
}
