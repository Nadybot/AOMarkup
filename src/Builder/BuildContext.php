<?php declare(strict_types=1);

namespace AO\Markup\Builder;

/**
 * State of one build: the ids of the windows that popup links open
 *
 * Every window a link is built for is recorded, in order. A window keeps its own id,
 * or gets the next free "window1", "window2", … if it has none or its id is taken.
 *
 * @internal
 */
final class BuildContext {
	/** @var list<WindowBuilder> */
	private array $windows = [];

	/** @var array<int,string> Window id by spl_object_id() of the window builder */
	private array $ids = [];

	/** @var array<string,true> */
	private array $taken = [];

	private int $next = 1;

	/**
	 * A context with the ids of these windows assigned in this order
	 *
	 * Own ids are reserved first, so a generated id never takes the id of a window
	 * that comes later.
	 *
	 * @param list<WindowBuilder> $windows
	 */
	public static function for(array $windows): self {
		$context = new self();
		foreach ($windows as $window) {
			if (isset($window->id) && !isset($context->taken[$window->id])) {
				$context->ids[spl_object_id($window)] = $window->id;
				$context->taken[$window->id] = true;
			}
		}
		foreach ($windows as $window) {
			$context->idOf($window);
		}
		return $context;
	}

	/** The id of a window; records the window if it is new */
	public function idOf(WindowBuilder $window): string {
		if (!in_array($window, $this->windows, true)) {
			$this->windows []= $window;
		}
		$key = spl_object_id($window);
		if (isset($this->ids[$key])) {
			return $this->ids[$key];
		}
		$id = $window->id;
		if ($id === null || isset($this->taken[$id])) {
			do {
				$id = 'window' . $this->next++;
			} while (isset($this->taken[$id]));
		}
		$this->taken[$id] = true;
		return $this->ids[$key] = $id;
	}

	/** @return list<WindowBuilder> The windows links were built for, in order */
	public function windows(): array {
		return $this->windows;
	}
}
