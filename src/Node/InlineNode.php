<?php declare(strict_types=1);

namespace AO\Markup\Node;

use AO\Markup\InlineContent;

/** A node that flows within a line of text */
interface InlineNode extends Node, InlineContent {
}
