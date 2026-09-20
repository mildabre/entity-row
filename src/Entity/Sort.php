<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Sort
{
    /**
     * @param class-string<EntityRow>|null $parentEntity
     */
    public function __construct(
        public readonly string $column = 'sort',
        public readonly int $initial = 0,
        public readonly ?string $parentEntity = null,
    ) {}
}