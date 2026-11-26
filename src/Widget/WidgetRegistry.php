<?php declare(strict_types=1);

namespace AO\Markup\Widget;

use function Safe\preg_match;

use AO\Markup\Node\Node;
use BackedEnum;
use InvalidArgumentException;
use Psr\Log\{LoggerInterface, NullLogger};
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/** Maps AoXML element names to widget classes */
final class WidgetRegistry {
	/** @var array<string,class-string<Widget>> */
	private array $widgets = [];

	/** @var array<string,AsWidget> */
	private array $definitions = [];

	/** A registry with all built-in widgets */
	public static function withBuiltins(): self {
		$registry = new self();
		$registry->register(Builtin\Slider::class);
		$registry->register(Builtin\Unavailable::class);
		$registry->register(Builtin\Param::class);
		$registry->register(Builtin\Choice::class);
		$registry->register(Builtin\Option::class);
		return $registry;
	}

	/** @param class-string<Widget> $class */
	public function register(string $class): void {
		$attrs = (new ReflectionClass($class))->getAttributes(AsWidget::class);
		if (count($attrs) === 0) {
			throw new InvalidArgumentException("{$class} has no #[AsWidget] attribute");
		}
		$definition = $attrs[0]->newInstance();
		$this->widgets[$definition->name] = $class;
		$this->definitions[$definition->name] = $definition;
	}

	public function has(string $name): bool {
		return isset($this->widgets[$name]);
	}

	/** @return ?class-string<Widget> */
	public function get(string $name): ?string {
		return $this->widgets[$name] ?? null;
	}

	/** The #[AsWidget] declaration of a widget */
	public function definition(string $name): ?AsWidget {
		return $this->definitions[$name] ?? null;
	}

	/**
	 * Create a widget node from AoXML attributes, hydrating the constructor parameters
	 *
	 * Attribute names are the parameter names, or their kebab-case form
	 * (markerText or marker-text). Values are converted to the parameter type
	 * (string, int, float, bool or a backed enum). Problems are logged; if a
	 * required parameter is missing or invalid, no widget is created.
	 *
	 * @param array<string,string|true> $attributes Values; true for boolean shorthand
	 */
	public function create(string $name, array $attributes, LoggerInterface $logger=new NullLogger(), Node ...$children): ?WidgetNode {
		$class = $this->widgets[$name] ?? null;
		if ($class === null) {
			$logger->warning("Unknown widget <{$name}>");
			return null;
		}
		$byName = [];
		foreach ($attributes as $attribute => $value) {
			$byName[self::camelCase($attribute)] = [$attribute, $value];
		}
		$args = [];
		$failed = false;
		$constructor = (new ReflectionClass($class))->getConstructor();
		foreach ($constructor?->getParameters() ?? [] as $param) {
			$given = $byName[$param->getName()] ?? null;
			unset($byName[$param->getName()]);
			if ($given === null) {
				if (!$param->isOptional()) {
					$logger->warning("<{$name}> needs the attribute \"{$param->getName()}\"");
					$failed = true;
				}
				continue;
			}
			[$attribute, $value] = $given;
			$converted = $this->convert($param, $value);
			if ($converted === null) {
				$shown = $value === true ? '(no value)' : "\"{$value}\"";
				$logger->warning("<{$name}>: invalid value {$shown} for attribute \"{$attribute}\"");
				$failed = $failed || !$param->isOptional();
				continue;
			}
			$args[$param->getName()] = $converted[0];
		}
		foreach ($byName as [$attribute]) {
			$logger->warning("<{$name}> has no attribute \"{$attribute}\"");
		}
		if ($failed) {
			return null;
		}
		return new WidgetNode(new $class(...$args), ...$children);
	}

	/**
	 * Convert an attribute value to the type of a parameter
	 *
	 * @param string|true $value
	 *
	 * @return ?array{mixed} The value, or null if it cannot be converted
	 */
	private function convert(ReflectionParameter $param, string|bool $value): ?array {
		$type = $param->getType();
		$typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'string';
		if ($typeName === 'bool') {
			return match ($value) {
				true, 'true' => [true],
				'false' => [false],
				default => null,
			};
		}
		if ($value === true) {
			return null;
		}
		if ($typeName === 'int') {
			return preg_match('/^[+-]?\d+$/', $value) === 1 ? [(int)$value] : null;
		}
		if ($typeName === 'float') {
			return is_numeric($value) ? [(float)$value] : null;
		}
		if (is_subclass_of($typeName, BackedEnum::class)) {
			$case = $typeName::tryFrom($value);
			return $case === null ? null : [$case];
		}
		return [$value];
	}

	private static function camelCase(string $attribute): string {
		return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $attribute))));
	}
}
