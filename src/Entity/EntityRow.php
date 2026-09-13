<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use BadMethodCallException;
use LogicException;
use Bite\EntityRow\Explorer\TypedExplorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

abstract class EntityRow extends ActiveRow
{
    /**
     * Deterministický ekvivalent ActiveRow::ref() - tabulku odvozuje z entity třídy,
     * FK sloupec buď převezme explicitně, nebo ho dohledá introspekcí schématu
     * (pokud existuje právě jedna FK vedoucí na cílovou tabulku).
     *
     * @template T of EntityRow
     * @param class-string<T> $entityClass
     * @return T|null
     */
    protected function refEntity(string $entityClass, ?string $fkColumn = null): ?EntityRow
    {
        $explorer = $this->typedExplorer();
        $table = $explorer->tableForEntity($entityClass);
        $fkColumn ??= $explorer->foreignKeyColumnFor($this->getTable()->getName(), $table);

        $result = parent::ref($table, $fkColumn);

        if ($result === null) {
            return null;
        }

        if (!$result instanceof EntityRow) {
            throw new LogicException(
                sprintf("%s: ref('%s', '%s') did not return an EntityRow instance, got %s.", static::class, $table, $fkColumn, $result::class)
            );
        }

        if (!$result instanceof $entityClass) {
            throw new LogicException(
                sprintf('%s: ref(\'%s\', \'%s\') was expected to return %s, got %s.', static::class, $table, $fkColumn, $entityClass, $result::class)
            );
        }

        return $result;
    }

    /**
     * Deterministický ekvivalent ActiveRow::related() - child tabulku odvozuje z entity
     * třídy, FK sloupec buď převezme explicitně, nebo ho dohledá introspekcí schématu
     * (pokud existuje právě jedna FK z child tabulky zpět na tuto tabulku).
     * @template T of EntityRow
     * @param class-string<T> $entityClass
     * @return Selection<T>
     */
    protected function relatedEntity(string $entityClass, ?string $fkColumn = null): Selection
    {
        $explorer = $this->typedExplorer();
        $childTable = $explorer->tableForEntity($entityClass);
        $localTable = $this->getTable()->getName();
        $fkColumn ??= $explorer->referencingColumnFor($localTable, $childTable);

        return parent::related($childTable, $fkColumn);
    }

    private function typedExplorer(): TypedExplorer
    {
        $explorer = $this->getTable()->getExplorer();

        if (!$explorer instanceof TypedExplorer) {
            throw new LogicException(
                sprintf('%s requires explorer of type %s, got %s.', static::class, TypedExplorer::class, $explorer::class)
            );
        }

        return $explorer;
    }

    public function ref(string $key, ?string $throughColumn = null): never
    {
        throw new BadMethodCallException("Method ActiveRow::ref() is disabled on EntityRow, use entity reference property instead.");
    }

    public function related(string $key, ?string $throughColumn = null): never
    {
        throw new BadMethodCallException("Method ActiveRow::related() is disabled on EntityRow, use entity related property instead.");
    }
}