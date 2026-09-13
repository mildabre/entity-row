<?php

declare(strict_types=1);

namespace Bite\EntityRow\DI;

use Bite\EntityRow\Explorer\TypedExplorer;
use Bite\EntityRow\Explorer\ExplorerLocator;
use Bite\EntityRow\Repository\EntityConvention;
use Bite\EntityRow\Repository\Repository;
use Bite\ServiceDiscovery\DI\ServiceDiscoveryExtension;
use LogicException;
use Nette\Database\Explorer;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use ReflectionClass;
use RuntimeException;
use stdClass;

final class EntityRowExtension extends CompilerExtension
{
    private const string DatabaseServicePrefix = 'database.';
    private const string ExplorerServiceSuffix = '.explorer';

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'enabled' => Expect::bool()->default(true),
        ]);
    }

    public function loadConfiguration(): void
    {
        /** @var stdClass $config */
        $config = $this->getConfig();
        if (!$config->enabled) {
            return;
        }

        $builder = $this->getContainerBuilder();

        $name = $this->prefix('explorerLocator');
        $builder->addDefinition($name)
            ->setType(ExplorerLocator::class)
            ->setAutowired(true);
    }

    public function beforeCompile(): void
    {
        /** @var stdClass $config */
        $config = $this->getConfig();
        if (!$config->enabled) {
            return;
        }

        $builder = $this->getContainerBuilder();

        $this->upgradeExplorers($builder);
        $this->markExplorersLazy($builder);
        [$explorersByConnection, $defaultConnection] = $this->collectExplorers($builder);

        $builder->getDefinition($this->prefix('explorerLocator'))
            ->setArguments([$explorersByConnection, $defaultConnection]);

        $sde = $this->getServiceDiscoveryExtension();

        $repositoryClasses = array_values(array_filter(
            $sde->getServices(),
            static fn(ReflectionClass $rc): bool => $rc->isSubclassOf(Repository::class),
        ));

        if ($repositoryClasses === []) {
            return;
        }

        $maps = $this->buildEntityMaps($repositoryClasses, $builder);

        foreach ($maps as $serviceName => $map) {
            $builder->getDefinition($serviceName)->addSetup('setEntityMap', [$map]);
        }
    }

    /**
     * Automaticky přepíše KAŽDOU službu typu Nette\Database\Explorer (registrovanou
     * DatabaseExtension pro každou connection v `database:` sekci) na TypedExplorer,
     * se zachováním PŮVODNÍCH tovární argumentů (connection, structure, conventions,
     * storage). Uživatel díky tomu nemusí ručně psát `class:`/`arguments:` override
     * v services.neon pro žádnou connection, ani pro nově přidané v budoucnu.
     */
    private function upgradeExplorers(ContainerBuilder $builder): void
    {
        foreach ($builder->getDefinitions() as $def) {
            if (!$def instanceof ServiceDefinition) {
                continue;
            }

            $factory = $def->getFactory();
            if ($factory->getEntity() !== Explorer::class) {
                continue;
            }

            $def->setFactory(new Statement(TypedExplorer::class, $factory->arguments));
            $def->setType(TypedExplorer::class);
        }
    }

    private function markExplorersLazy(ContainerBuilder $builder): void
    {
        if (PHP_VERSION_ID < 80400) {
            return;
        }

        foreach ($builder->findByType(TypedExplorer::class) as $def) {
            if ($def instanceof ServiceDefinition) {
                $def->lazy = true;
            }
        }
    }

    /**
     * @return array{0: array<string, Statement>, 1: string}  [connectionName => @service, defaultConnectionName]
     */
    private function collectExplorers(ContainerBuilder $builder): array
    {
        $explorers = [];
        $autowiredConnection = null;

        foreach ($builder->findByType(TypedExplorer::class) as $serviceName => $def) {
            $connectionName = $this->connectionNameFromServiceName($serviceName);
            $explorers[$connectionName] = new Statement('@' . $serviceName);

            if ($def instanceof ServiceDefinition && $def->getAutowired() === true) {
                if ($autowiredConnection !== null) {
                    throw new LogicException(sprintf(
                        '%s, multiple autowired services of type %s found (%s, %s). Only one may be autowired: true.',
                        self::class, TypedExplorer::class, $autowiredConnection, $connectionName
                    ));
                }
                $autowiredConnection = $connectionName;
            }
        }

        if ($explorers === []) {
            throw new LogicException(sprintf(
                '%s, no service of type %s found. Configure at least one database connection with class: %s.',
                self::class, TypedExplorer::class, TypedExplorer::class
            ));
        }

        if ($autowiredConnection === null) {
            throw new LogicException(sprintf(
                '%s, no autowired service of type %s found. Exactly one database connection must have autowired: true.',
                self::class, TypedExplorer::class
            ));
        }

        return [$explorers, $autowiredConnection];
    }

    private function connectionNameFromServiceName(string $serviceName): string
    {
        return substr($serviceName, strlen(self::DatabaseServicePrefix), -strlen(self::ExplorerServiceSuffix));
    }

    private function getServiceDiscoveryExtension(): ServiceDiscoveryExtension
    {
        $sdeExtensions = $this->compiler->getExtensions(ServiceDiscoveryExtension::class);
        if (!$sdeExtensions) {
            throw new RuntimeException(
                'ServiceDiscoveryExtension not registered, add it to your .neon file - before EntityRowExtension registration.'
            );
        }

        $extensions = array_keys($this->compiler->getExtensions());
        $sdeKey = array_key_first($sdeExtensions);

        if (array_search($sdeKey, $extensions, true) > array_search($this->name, $extensions, true)) {
            throw new LogicException('ServiceDiscoveryExtension must be registered before EntityRowExtension.');
        }

        /** @var ServiceDiscoveryExtension $sde */
        $sde = reset($sdeExtensions);
        return $sde;
    }

    /**
     * @param list<ReflectionClass> $repositoryClasses
     * @return array<string, array<string, class-string>>  [explorerServiceName => [table => entityClass]]
     */
    private function buildEntityMaps(array $repositoryClasses, ContainerBuilder $builder): array
    {
        $maps = [];
        $tableOwners = []; // "serviceName:table" => repository FQN, pro hlášku při konfliktu
        $entityOwners = []; // "serviceName:entityClass" => repository FQN, pro hlášku při konfliktu

        foreach ($repositoryClasses as $rc) {
            $serviceName = $this->resolveExplorerServiceName($rc, $builder);
            $table = EntityConvention::resolveTable($rc);
            $entityClass = EntityConvention::resolveEntityClass($rc);

            $tableKey = $serviceName . ':' . $table;
            if (isset($tableOwners[$tableKey]) && $tableOwners[$tableKey] !== $rc->name) {
                throw new LogicException(sprintf(
                    "Table '%s' on service '%s' is mapped by multiple repositories: %s and %s.",
                    $table, $serviceName, $tableOwners[$tableKey], $rc->name
                ));
            }
            $tableOwners[$tableKey] = $rc->name;

            $ownerKey = $serviceName . ':' . $entityClass;
            if (isset($entityOwners[$ownerKey]) && $entityOwners[$ownerKey] !== $rc->name) {
                throw new LogicException(sprintf(
                    "Entity %s on service '%s' is used by multiple repositories: %s and %s.",
                    $entityClass, $serviceName, $entityOwners[$ownerKey], $rc->name
                ));
            }
            $entityOwners[$ownerKey] = $rc->name;

            $maps[$serviceName][$table] = $entityClass;
        }

        return $maps;
    }

    private function resolveExplorerServiceName(ReflectionClass $repositoryRc, ContainerBuilder $builder): string
    {
        $connectionName = EntityConvention::resolveConnectionName($repositoryRc);

        if ($connectionName !== null) {
            $serviceName = self::DatabaseServicePrefix . $connectionName . self::ExplorerServiceSuffix;

            if (!$builder->hasDefinition($serviceName)) {
                throw new LogicException(sprintf(
                    "%s, #[Connection('%s')] references service '%s' which is not configured. Check your 'database:' section in di.neon.",
                    $repositoryRc->name, $connectionName, $serviceName
                ));
            }

            $def = $builder->getDefinition($serviceName);
            $this->assertIsTypedExplorer($def, $serviceName, $repositoryRc->name);

            return $serviceName;
        }

        $candidates = $builder->findByType(TypedExplorer::class);
        $autowired = array_filter(
            $candidates,
            static fn(ServiceDefinition $d): bool => $d->getAutowired() === true,
        );

        if (count($autowired) !== 1) {
            throw new LogicException(sprintf(
                "%s, no #[Connection] specified and no single autowired service of type %s found. " .
                "Either add #[Connection('name')] to the repository, or configure exactly one database connection with autowired: true.",
                $repositoryRc->name, TypedExplorer::class
            ));
        }

        return array_key_first($autowired);
    }

    private function assertIsTypedExplorer(Definition $def, string $serviceName, string $repositoryClass): void
    {
        $type = $def->getType();
        if ($type === null || !($type === TypedExplorer::class || is_subclass_of($type, TypedExplorer::class))) {
            throw new LogicException(sprintf(
                "%s, service '%s' must be of type %s, got %s. Set 'class: %s' for this connection in di.neon.",
                $repositoryClass, $serviceName, TypedExplorer::class, $type ?? 'unknown', TypedExplorer::class
            ));
        }
    }
}