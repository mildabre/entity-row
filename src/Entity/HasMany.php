<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class HasMany
{
    /**
     * @param class-string<EntityRow> $entity
     */
    public function __construct(
        public readonly string $entity,
    ) {}
}