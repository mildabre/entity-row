<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

use BadMethodCallException;
use Bite\EntityRow\Reflector\ReflectionClassTrait;
use DateTimeInterface;
use LogicException;
use Bite\EntityRow\Entity\EntityRow;
use Bite\EntityRow\Explorer\ExplorerLocator;
use Bite\EntityRow\Explorer\TypedExplorer;
use Bite\EntityRow\Reflector\Reflectable;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use RuntimeException;

/**
 * @template T of EntityRow
 */
abstract class Repository implements Reflectable
{
    use ReflectionClassTrait;

    public static function getReflector(): RepositoryReflector         // no instance required, subclasses may narrow return type
    {
        return RepositoryReflector::for(static::class);
    }

    private ExplorerLocator $explorerLocator;

    final public function injectExplorerLocator(ExplorerLocator $explorerLocator): void
    {
        $this->explorerLocator = $explorerLocator;
    }

    protected TypedExplorer $explorer {
        get => $this->explorer ??= $this->explorerLocator->get(static::getReflector()->connectionName);
    }

    /**
     * @return  T|null
     */
    public function find(?int $id): ?EntityRow
    {
        return $id !== null ? $this->assertEntityRow($this->findAll()->get($id)) : null;
    }

    /**
     * @return  T
     */
    public function findOrFail(?int $id, OnFail $onFail, ?string $message = null): EntityRow
    {
        return $this->find($id) ?? throw match ($onFail) {
            OnFail::BadRequestException => new BadRequestException($message ?? 'Page not found'),
            OnFail::RuntimeException => new RuntimeException($message ?? 'Row not found'),
        };
    }

    /**
     * @return  T|null
     */
    public function findOneBy(string $column, string|int|float|bool|array|DateTimeInterface|null $value): ?EntityRow
    {
        return $this->assertEntityRow($this->findAll()->where($column, $value)->fetch());
    }

    /**
     * @return  T
     */
    public function findOneByOrFail(string $column, string|int|float|bool|array|DateTimeInterface|null $value, bool $rowNotFoundException = false): EntityRow
    {
        return $this->findOneBy($column, $value) ?? throw ($rowNotFoundException ? new RowNotFoundException() : new BadRequestException());
    }

    /**
     * @return  Selection<T>
     */
    public function findAll(): Selection
    {
        return $this->explorer->table(static::getReflector()->table);
    }

    /**
     * @return  Selection<T>
     */
    public function findBy(string|array $condition, mixed ...$params): Selection
    {
        return $this->findAll()->where($condition, ...$params);
    }

    /**
     * @param array<string, mixed> $data  exactly one row - column => value
     * @return T
     */
    public function insertOne(array $data): EntityRow
    {
        if ($data === []) {
            throw new LogicException(sprintf('%s::insertOne() called with empty $data.', static::class));
        }

        if (array_is_list($data)) {
            throw new LogicException(sprintf(
                '%s::insertOne() has got a list of rows instead of one row (column => value). For inserting multiple rows use insertMulti().',
                static::class,
            ));
        }

        $row = $this->rawInsert($data);
        $entityClass = static::getReflector()->entityClass;

        if (!$row instanceof EntityRow || !$row instanceof $entityClass) {
            throw new LogicException(sprintf(
                '%s::insertOne() - insert did not return an instance of %s (got %s). Table %s must have an autoincrement primary key, or you must supply the primary key value(s) explicitly in $data.',
                static::class, $entityClass, get_debug_type($row), static::getReflector()->table,
            ));
        }
        return $row;
    }

    /**
     * @param iterable<int, array<string, mixed>>|Selection<T> $rows
     */
    public function insertMulti(iterable|Selection $rows): int
    {
        if ($rows instanceof Selection) {
            $result = $this->rawInsert($rows);

            return is_int($result) ? $result : throw new LogicException(sprintf(
                '%s::insertMulti() - insert-select unexpectedly did not return a row count.',
                static::class,
            ));
        }

        $rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);

        if ($rows === []) {
            return 0;
        }

        $this->rawInsert($rows);    // return value of rawInsert() is intentionally ignored because it's unreliable

        return count($rows);
    }

    /**
     * @deprecated Selection::insert() is deprecated - use insertOne() for a single row or insertMulti() for multiple rows or insert-select.
     */
    public function insert(iterable $data): never
    {
        throw new BadMethodCallException(sprintf('%s::insert() is not recommended to use - use insertOne() or insertMulti().',static::class));
    }

    private function assertEntityRow(?ActiveRow $row): ?EntityRow
    {
        if ($row === null) {
            return null;
        }

        return $row instanceof EntityRow ? $row : throw new LogicException(sprintf(
            '%s: row from table "%s" is not an instance of %s (got %s). Check the entity map configuration for this connection.',
            static::class, static::getReflector()->table, EntityRow::class, get_debug_type($row),
        ));
    }

    /**
     * @param iterable<string, mixed>|Selection<T> $data
     */
    private function rawInsert(iterable|Selection $data): array|int|ActiveRow
    {
        return $this->findAll()->insert($data);
    }
}