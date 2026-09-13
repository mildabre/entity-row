<?php

declare(strict_types=1);

namespace Bite\EntityRow\Explorer;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use LogicException;

final class TypedExplorer extends Explorer
{
    /**
     * @var array<string, class-string<ActiveRow>>
     */
    private array $entityMap = [];

    /**
     * @var array<class-string, string>
     */
    private array $tableByEntity = [];

    /**
     * @var array<string, string>  "localTable->targetTable" => fkColumn, per-instance cache
     */
    private array $fkColumnCache = [];

    /**
     * @var array<string, string>  "parentTable<-childTable" => fkColumn, per-instance cache
     */
    private array $referencingColumnCache = [];

    /**
     * @param array<string, class-string<ActiveRow>> $map
     */
    public function setEntityMap(array $map): void
    {
        $this->entityMap = $map;
        $this->tableByEntity = array_flip($map);
    }

    public function createActiveRow(array $data, Selection $selection): ActiveRow
    {
        $class = $this->entityMap[$selection->getName()] ?? ActiveRow::class;

        return new $class($data, $selection);
    }

    /**
     * @param class-string $entityClass
     */
    public function tableForEntity(string $entityClass): string
    {
        return $this->tableByEntity[$entityClass]
            ?? throw new LogicException(sprintf('No table mapped for entity %s.', $entityClass));
    }

    /**
     * Najde jméno FK sloupce z $localTable vedoucího na $targetTable pomocí introspekce
     * reálného DB schématu (Structure::getBelongsToReference), ne konvence jmen.
     * Vyžaduje přesně jednu shodu, jinak throw.
     */
    public function foreignKeyColumnFor(string $localTable, string $targetTable): string
    {
        $cacheKey = $localTable . '->' . $targetTable;
        if (isset($this->fkColumnCache[$cacheKey])) {
            return $this->fkColumnCache[$cacheKey];
        }

        // [column => targetTable], per-request cachováno navíc Nette\Database\Structure samotnou
        $belongsTo = $this->getStructure()->getBelongsToReference($localTable);

        $matches = array_keys(array_filter(
            $belongsTo,
            static fn(string $refTable): bool => strtolower($refTable) === strtolower($targetTable),
        ));

        if (count($matches) === 0) {
            throw new LogicException(sprintf(
                "No foreign key found from table '%s' to table '%s'. Specify the column explicitly.",
                $localTable, $targetTable
            ));
        }

        if (count($matches) > 1) {
            throw new LogicException(sprintf(
                "Ambiguous foreign key from table '%s' to table '%s' (columns: %s). Specify the column explicitly.",
                $localTable, $targetTable, implode(', ', $matches)
            ));
        }

        return $this->fkColumnCache[$cacheKey] = $matches[0];
    }

    /**
     * Najde jméno FK sloupce na $childTable, který odkazuje zpět na $parentTable
     * (opačný směr než foreignKeyColumnFor - pro related()/refRelated()).
     */
    public function referencingColumnFor(string $parentTable, string $childTable): string
    {
        $cacheKey = $parentTable . '<-' . $childTable;
        if (isset($this->referencingColumnCache[$cacheKey])) {
            return $this->referencingColumnCache[$cacheKey];
        }

        // [childTable => [columns on childTable pointing to $parentTable]]
        $hasMany = $this->getStructure()->getHasManyReference($parentTable);

        $columns = null;
        foreach ($hasMany as $refTable => $cols) {
            if (strtolower($refTable) === strtolower($childTable)) {
                $columns = $cols;
                break;
            }
        }

        if ($columns === null || count($columns) === 0) {
            throw new LogicException(sprintf(
                "No foreign key found from table '%s' to table '%s'. Specify the column explicitly.",
                $childTable, $parentTable
            ));
        }

        if (count($columns) > 1) {
            throw new LogicException(sprintf(
                "Ambiguous foreign key from table '%s' to table '%s' (columns: %s). Specify the column explicitly.",
                $childTable, $parentTable, implode(', ', $columns)
            ));
        }

        return $this->referencingColumnCache[$cacheKey] = $columns[0];
    }
}