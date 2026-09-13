<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

use LogicException;
use Bite\EntityRow\Entity\Entity;
use Bite\EntityRow\Entity\EntityRow;
use ReflectionAttribute;
use ReflectionClass;

final class EntityConvention
{
    public const string RequiredSuffix = 'Repository';

    public static function resolveTable(ReflectionClass $rc): string
    {
        $attribute = self::findAttribute($rc, Table::class);

        if ($attribute) {
            $table = $attribute->newInstance()->table;
            if ($table === '') {
                throw new LogicException(sprintf('%s, parameter $table of #[Table] cannot be empty string.', $rc->name));
            }
            return $table;
        }

        return self::conventionalTableName($rc);
    }

    public static function resolvePrefix(ReflectionClass $rc): string
    {
        if (!str_ends_with($rc->getShortName(), self::RequiredSuffix)) {
            throw new LogicException(sprintf('%s, classname must end with "%s".', $rc->name, self::RequiredSuffix));
        }

        return substr($rc->getShortName(), 0, -strlen(self::RequiredSuffix));
    }

    /**
     * @return class-string<EntityRow>
     */
    public static function resolveEntityClass(ReflectionClass $rc): string
    {
        $explicit = self::findAttribute($rc, WithEntity::class);
        $entityClass = $explicit
            ? $explicit->newInstance()->class
            : $rc->getNamespaceName() . '\\' . self::resolvePrefix($rc);

        if (!class_exists($entityClass)) {
            throw new LogicException(sprintf("%s, entity  '%s' not found.", $rc->name, $entityClass));
        }

        $entityRc = new ReflectionClass($entityClass);

        if (!$entityRc->getAttributes(Entity::class)) {
            throw new LogicException(sprintf('%s, class %s is missing #[Entity] attribute.', $rc->name, $entityClass));
        }

        if (!$entityRc->isSubclassOf(EntityRow::class)) {
            throw new LogicException(sprintf('%s, class %s must extend %s.', $rc->name, $entityClass, EntityRow::class));
        }

        if (!$entityRc->isFinal()) {
            throw new LogicException(sprintf('%s, class %s must be declared final.', $rc->name, $entityClass));
        }

        return $entityClass;
    }

    public static function resolveConnectionName(ReflectionClass $rc): ?string
    {
        $attribute = self::findAttribute($rc, Connection::class);
        return $attribute?->newInstance()->name;
    }

    private static function conventionalTableName(ReflectionClass $rc): string
    {
        $prefix = self::resolvePrefix($rc);
        $snake = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $prefix);
        $snake = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $snake);

        return strtolower($snake);
    }

    private static function findAttribute(ReflectionClass $rc, string $attrClass): ?ReflectionAttribute
    {
        while ($rc !== false) {
            $attrs = $rc->getAttributes($attrClass);
            if ($attrs) {
                return $attrs[0];
            }
            $rc = $rc->getParentClass();
        }

        return null;
    }
}