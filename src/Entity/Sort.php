<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Sort
{
    public function __construct(
        public readonly string $column = 'sort',
        public readonly int $initial = 0,
        public readonly ?string $parentKey = null,
    ) {}
}