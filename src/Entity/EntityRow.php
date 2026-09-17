<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use BadMethodCallException;
use LogicException;
use Bite\EntityRow\Explorer\TypedExplorer;
use Nette\Database\Explorer;
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

    /**
     * @var array<string, array<string, true>>  table name => column name => true
     */
    private static array $columnsCache = [];

    public static function tableHasColumn(Explorer $explorer, string $table, string $column): bool          // safe inspection of column presence
    {
        $columns = self::$columnsCache[$table] ??= array_column(
            $explorer->getStructure()->getColumns($table),
            null,
            'name',
        );

        return isset($columns[$column]);
    }

    public function hasColumn(string $column): bool
    {
        return self::tableHasColumn($this->getExplorer(), $this->getTable()->getName(), $column);
    }

    public function readColumn(string $column): mixed                               // safe reading of any column
    {
        if (!$this->hasColumn($column)) {
            throw new LogicException(sprintf(
                '%s: sloupec "%s" na tabulce "%s" neexistuje.',
                static::class, $column, $this->getTable()->getName(),
            ));
        }

        return $this->{$column};
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