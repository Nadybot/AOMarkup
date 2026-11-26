<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

use AO\Markup\Node\Node;

/**
 * One line of BBML while blocks are built, or a block that is already finished
 *
 * @internal
 */
final class BbmlLine {
	public const LINE = 'line';
	public const HEADING = 'heading';
	public const BLANK = 'blank';
	public const BLOCK = 'block';

	/**
	 * @param self::LINE|self::HEADING|self::BLANK|self::BLOCK $kind
	 * @param Node                                             $node   The content of a line or heading, or the block
	 * @param list<BbmlToken>                                  $tokens The tokens of a line, without its indentation
	 * @param list<BbmlLine>                                   $inner  The lines of a <permheader> or <center> block
	 */
	public function __construct(
		public readonly int $level,
		public readonly string $kind,
		public readonly Node $node,
		public readonly array $tokens=[],
		public readonly array $inner=[],
	) {
	}
}
