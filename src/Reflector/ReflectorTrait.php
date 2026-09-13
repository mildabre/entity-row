<?php

declare(strict_types=1);

namespace Bite\EntityRow\Reflector;

use Nette\DI\Attributes\Inject;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

trait ReflectorTrait
{
    /**
     * @var array<string, static>
     */
    private static array $classCache = [];

    protected readonly ReflectionClass $reflection;

    /**
     * @param class-string $class
     */
    public static function for(string $class): static
    {
        $cacheKey = static::class . ':' . $class;
        return self::$classCache[$cacheKey] ??= new static($class::reflection());
    }

    protected function __construct(ReflectionClass $reflection)
    {
        $this->reflection = $reflection;
    }

    abstract public function getRequiredSuffix(): string;

    public string $shortName {
        get => $this->reflection->getShortName();
    }

    public string $prefix {
        get => substr($this->shortName, 0, - strlen($this->getRequiredSuffix()));
    }

    public bool $isClassnameValid {
        get => str_ends_with($this->shortName, $this->getRequiredSuffix());
    }

    public function hasTrait(string $class): bool
    {
        $rc = $this->reflection;

        while ($rc !== false) {
            if (in_array($class, $rc->getTraitNames())) {
                return true;
            }
            $rc = $rc->getParentClass();
        }

        return false;
    }

    public function getAttribute(string $class): ?ReflectionAttribute
    {
        $rc = $this->reflection;

        while ($rc !== false) {
            $attributes = $rc->getAttributes($class);
            if ($attributes) {
                return $attributes[0];
            }
            $rc = $rc->getParentClass();
        }

        return null;
    }

    public function hasAttribute(string $class): bool
    {
        return $this->getAttribute($class) !== null;
    }

    /**
     * @return list<ReflectionProperty>
     */
    public function getInjectedProperties(): array
    {
        $properties = array_merge($this->scanPromotedProperties(), $this->scanAttributeInjectedProperties());
        return array_values(array_reduce(
            $properties,
            fn(array $unique, ReflectionProperty $property) => $unique + [$property->name => $property],
            [],
        ));
    }

    private function scanPromotedProperties(): array
    {
        $constructor = $this->reflection->getConstructor();

        if (!$constructor) {
            return [];
        }

        $declaringClass = $constructor->getDeclaringClass();

        $parameters = array_filter(
            $constructor->getParameters(),
            fn(ReflectionParameter $param) => $param->isPromoted() && $param->getType() instanceof ReflectionNamedType && !$param->getType()->isBuiltIn()
        );

        return array_map(fn($parameter) => $declaringClass->getProperty($parameter->name), $parameters);
    }

    private function scanAttributeInjectedProperties(): array
    {
        return array_filter(
            $this->reflection->getProperties(),
            fn(ReflectionProperty $p) => $p->getAttributes(Inject::class) && $p->getType() instanceof ReflectionNamedType && !$p->getType()->isBuiltIn()
        );
    }
}