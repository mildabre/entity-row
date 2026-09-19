<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Entity
{
    public function __construct(
        public readonly ?string $table = null,
        public readonly ?string $repository = null,
    ) {}
}