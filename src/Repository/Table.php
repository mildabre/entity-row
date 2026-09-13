<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final class Table
{
    public function __construct(
        public readonly string $table,
    ) {
        if ($this->table === '') {
            throw new InvalidArgumentException('Table name cannot be empty string');
        }
    }
}