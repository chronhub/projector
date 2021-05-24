<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Projector\Status;

interface Repository
{
    public function initiate(): void;

    public function loadState(): void;

    public function stop(): void;

    public function startAgain(): void;

    public function loadStatus(): Status;

    public function persist(): void;

    public function reset(): void;

    public function delete(bool $withEmittedEvents): void;

    public function exists(): bool;

    public function acquireLock(): void;

    public function updateLock(): void;

    public function releaseLock(): void;

    public function getStreamName(): string;
}
