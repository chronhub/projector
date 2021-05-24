<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Projector\Support\Contracts\Support\ReadModel;

interface Manager
{
    public function createQuery(array $options = []): ProjectorFactory;

    public function createProjection(string $streamName, array $options = []): ProjectorFactory;

    public function createReadModelProjection(string $streamName, ReadModel $readModel, array $options = []): ProjectorFactory;

    public function stop(string $streamName): void;

    public function reset(string $streamName): void;

    public function delete(string $streamName, bool $deleteEmittedEvents): void;

    public function queryScope(): ProjectionQueryScope;

    public function statusOf(string $name): string;

    public function streamPositionsOf(string $name): array;

    public function stateOf(string $name): array;

    public function filterNamesOf(string ...$names): array;

    public function exists(string $name): bool;
}
