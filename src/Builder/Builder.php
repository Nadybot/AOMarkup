<?php declare(strict_types=1);

namespace AO\Markup\Builder;

/**
 * Something that builds nodes of the document tree
 *
 * Builders are mutable; build() makes the immutable nodes. A builder used as content
 * of another builder is built when that one is built.
 */
interface Builder {
}
