<?php

declare(strict_types=1);

namespace Bite\EntityRow\Reflector;

use ReflectionClass;

trait ReflectionClassTrait
{
    private static array $reflectionCache = [];

    public static function getReflectionClass(): ReflectionClass
    {
        return self::$reflectionCache[static::class] ??= new ReflectionClass(static::class);
    }
}