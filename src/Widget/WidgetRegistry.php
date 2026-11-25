<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use AO\Markup\Exceptions\NotImplementedException;
use AO\Markup\Node\Node;
use InvalidArgumentException;
use ReflectionClass;

/** Maps AoXML element names to widget classes */
final class WidgetRegistry {
	/** @var array<string,class-string<Widget>> */
	private array $widgets = [];

	/** A registry with all built-in widgets */
	public static function withBuiltins(): self {
		$registry = new self();
		$registry->register(Builtin\Slider::class);
		$registry->register(Builtin\Unavailable::class);
		$registry->register(Builtin\Param::class);
		return $registry;
	}

	/** @param class-string<Widget> $class */
	public function register(string $class): void {
		$attrs = (new ReflectionClass($class))->getAttributes(AsWidget::class);
		if (count($attrs) === 0) {
			throw new InvalidArgumentException("{$class} has no #[AsWidget] attribute");
		}
		$this->widgets[$attrs[0]->newInstance()->name] = $class;
	}

	public function has(string $name): bool {
		return isset($this->widgets[$name]);
	}

	/** @return ?class-string<Widget> */
	public function get(string $name): ?string {
		return $this->widgets[$name] ?? null;
	}

	/**
	 * Create a widget node from AoXML attributes, hydrating the constructor parameters
	 *
	 * @param array<string,string> $attributes
	 */
	public function create(string $name, array $attributes, Node ...$children): WidgetNode {
		throw NotImplementedException::for('Widget hydration');
	}
}
