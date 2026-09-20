<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

use Bite\EntityRow\Entity\EntityRow;
use Bite\EntityRow\Reflector\Reflector;
use Bite\EntityRow\Reflector\ReflectorTrait;

class RepositoryReflector implements Reflector
{
    use ReflectorTrait;

    public function getRequiredSuffix(): string
    {
        return 'Repository';
    }

    public string $table {
        get => $this->table ??= EntityConvention::resolveTable($this->reflection);
    }

    /**
     * @var class-string<EntityRow>
     */
    public string $entityClass {
        get => $this->entityClass ??= EntityConvention::resolveEntityClass($this->reflection);
    }

    private bool $cnInitialized = false;
    private ?string $cachedValue = null;

    public ?string $connectionName {
        get {
            if (!$this->cnInitialized) {
                $this->cachedValue = EntityConvention::resolveConnectionName($this->reflection);
                $this->cnInitialized = true;
            }
            return $this->cachedValue;
        }
    }
}