<?php

declare(strict_types=1);

namespace Bite\EntityRow\Reflector;

use ReflectionClass;

interface Reflectable
{
    public static function getReflection(): ReflectionClass;

    public static function getReflector(): Reflector;
}