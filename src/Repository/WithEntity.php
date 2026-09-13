<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WithEntity
{
    public function __construct(
        public readonly string $class,
    ) {}
}