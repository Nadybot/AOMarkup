<?php declare(strict_types=1);

namespace AO\Markup\Node;

/**
 * A possible page break, never a forced one
 *
 * If a window has any, the Paginator ends pages only at them, and only where needed.
 */
final class PageBreak implements BlockNode {
}
