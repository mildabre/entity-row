<?php

declare(strict_types=1);

namespace Bite\EntityRow\Explorer;

use LogicException;

final class ExplorerLocator
{
    /**
     * @param array<string, TypedExplorer> $explorers  connectionName => TypedExplorer (lazy)
     */
    public function __construct(
        private readonly array $explorers,
        private readonly string $defaultConnection,
    ) {
    }

    public function get(?string $connectionName): TypedExplorer
    {
        $key = $connectionName ?? $this->defaultConnection;

        return $this->explorers[$key]
            ?? throw new LogicException(sprintf("No TypedExplorer registered for connection '%s'.", $key));
    }
}