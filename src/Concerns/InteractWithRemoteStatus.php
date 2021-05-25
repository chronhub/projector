<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Chronhub\Projector\Status;
use Chronhub\Projector\Support\Contracts\Repository;

trait InteractWithRemoteStatus
{
    public function __construct(protected Repository $repository)
    {
    }

    protected function stopOnLoadingRemoteStatus(bool $keepRunning): bool
    {
        return $this->discoverRemoteProjectionStatus(true, $keepRunning);
    }

    protected function reloadRemoteStatus(bool $keepRunning): void
    {
        $this->discoverRemoteProjectionStatus(false, $keepRunning);
    }

    private function discoverRemoteProjectionStatus(bool $firstExecution, bool $keepRunning): bool
    {
        return match ($this->repository->loadStatus()) {
            Status::STOPPING() => $this->markAsStop($firstExecution),
            Status::RESETTING() => $this->markAsReset($firstExecution, $keepRunning),
            Status::DELETING() => $this->markAsDelete($firstExecution, false),
            Status::DELETING_EMITTED_EVENTS() => $this->markAsDelete($firstExecution, true),
            default => false
        };
    }

    private function markAsStop(bool $firstExecution): bool
    {
        if ($firstExecution) {
            $this->repository->loadState();
        }

        $this->repository->stop();

        return $firstExecution;
    }

    private function markAsReset(bool $firstExecution, bool $keepRunning): bool
    {
        $this->repository->reset();

        if ( ! $firstExecution && $keepRunning) {
            $this->repository->startAgain();
        }

        return false;
    }

    private function markAsDelete(bool $firstExecution, bool $withEmittedEvents): bool
    {
        $this->repository->delete($withEmittedEvents);

        return $firstExecution;
    }
}
