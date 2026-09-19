<?php

declare(strict_types=1);

namespace Bite\EntityRow\Entity;

use BadMethodCallException;
use LogicException;
use Bite\EntityRow\Explorer\TypedExplorer;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use ReflectionClass;

abstract class EntityRow extends ActiveRow
{
    /**
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

    public static function hasTableColumn(Explorer $explorer, string $table, string $column): bool          // safe inspection of column presence
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
        return self::hasTableColumn($this->getExplorer(), $this->getTable()->getName(), $column);
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

    /**
     * @var array<class-string<EntityRow>, SortColumn|null>
     */
    private static array $sortColumnCache = [];

    public static function getSortColumn(): ?SortColumn
    {
        if (!array_key_exists(static::class, self::$sortColumnCache)) {
            $attribute = new ReflectionClass(static::class)->getAttributes(SortColumn::class)[0] ?? null;
            self::$sortColumnCache[static::class] = $attribute?->newInstance();
        }

        return self::$sortColumnCache[static::class];
    }

    /**
     * @deprecated
     */
    public function ref(string $key, ?string $throughColumn = null): never
    {
        throw new BadMethodCallException("Method ActiveRow::ref() is disabled on EntityRow, use entity reference property instead.");
    }

    /**
     * @deprecated
     */
    public function related(string $key, ?string $throughColumn = null): never
    {
        throw new BadMethodCallException("Method ActiveRow::related() is disabled on EntityRow, use entity related property instead.");
    }
}