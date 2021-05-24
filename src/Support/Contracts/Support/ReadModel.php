<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts\Support;

interface ReadModel
{
    public function initialize(): void;

    public function stack(string $operation, mixed ...$arguments): void;

    public function persist(): void;

    public function isInitialized(): bool;

    public function reset(): void;

    public function down(): void;
}
