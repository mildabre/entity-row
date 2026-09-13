<?php

declare(strict_types=1);

namespace Bite\EntityRow\Reflector;

use ReflectionAttribute;
use ReflectionProperty;

interface Reflector
{
    public string $shortName { get; }

    public string $prefix { get; }

    public bool $isClassnameValid { get; }

    public function getRequiredSuffix(): string;

    public function hasTrait(string $class): bool;

    public function hasAttribute(string $class): bool;

    public function getAttribute(string $class): ?ReflectionAttribute;

    /**
     * @return list<ReflectionProperty>
     */
    public function getInjectedProperties(): array;
}