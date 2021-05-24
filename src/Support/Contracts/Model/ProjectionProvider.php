<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts\Model;

interface ProjectionProvider
{
    public function createProjection(string $name, string $status): bool;

    public function updateProjection(string $name, array $data): bool;

    public function acquireLock(string $name, string $status, string $lockedUntil, string $now): bool;

    public function deleteProjectionByName(string $name): bool;

    public function findByName(string $name): ?ProjectionModel;

    public function projectionExists(string $name): bool;

    /**
     * @return string[]
     */
    public function findByNames(string ...$names): array;
}
