<?php

declare(strict_types=1);

namespace Bite\EntityRow\Reflector;

use ReflectionClass;

trait CheckReflectable
{
    /**
     * @param class-string|null $class
     * @phpstan-assert-if-true class-string<Reflectable> $class
     */
    protected function isReflectable(?string $class): bool
    {
        return $class !== null && is_a($class, Reflectable::class, true) && $class !== Reflectable::class;
    }

    /**
     * @param class-string $class
     */
    protected function reflectionFor(string $class): ReflectionClass
    {
        return $this->isReflectable($class) ? $class::getReflectionClass() : new ReflectionClass($class);
    }
}