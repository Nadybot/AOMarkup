<?php declare(strict_types=1);

namespace AO\Markup\Import\Bbml;

use AO\Markup\Node\{Node, StyleName};
use Closure;

/**
 * The open inline tags of one line while it is built
 *
 * A tag is [kind, value]: ['style', StyleName], ['color', '#RRGGBB'], ['black', ''],
 * ['u', ''], ['i', ''] or ['link', href].
 *
 * @internal
 */
final class InlineStack {
	/** @var list<array{array{string,string|StyleName},list<Node>}> */
	private array $frames = [];

	/** @var list<Node> */
	private array $root = [];

	/**
	 * @param list<array{string,string|StyleName}>                     $open  Tags still open from the previous line
	 * @param Closure(array{string,string|StyleName},list<Node>):?Node $build Builds the node of a closed tag
	 */
	public function __construct(
		array $open,
		private Closure $build,
	) {
		foreach ($open as $tag) {
			$this->frames []= [$tag, []];
		}
	}

	public function append(Node $node): void {
		$last = count($this->frames) - 1;
		if ($last >= 0) {
			$this->frames[$last][1] []= $node;
		} else {
			$this->root []= $node;
		}
	}

	/** @param array{string,string|StyleName} $tag */
	public function open(array $tag): void {
		$this->frames []= [$tag, []];
	}

	/** Close the innermost open tag of one of these kinds, and all tags inside it; false if there is none */
	public function closeLast(string ...$kinds): bool {
		for ($i = count($this->frames) - 1; $i >= 0; $i--) {
			if (in_array($this->frames[$i][0][0], $kinds, true)) {
				while (count($this->frames) > $i) {
					$this->close();
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Close all tags at the end of the line
	 *
	 * @return array{list<Node>,list<array{string,string|StyleName}>} The nodes, and the tags to open again on the next line
	 */
	public function finish(): array {
		$open = array_map(static fn (array $frame): array => $frame[0], $this->frames);
		while (count($this->frames) > 0) {
			$this->close();
		}
		return [$this->root, $open];
	}

	private function close(): void {
		$frame = array_pop($this->frames);
		if ($frame === null) {
			return;
		}
		$node = ($this->build)($frame[0], $frame[1]);
		if ($node !== null) {
			$this->append($node);
		}
	}
}
