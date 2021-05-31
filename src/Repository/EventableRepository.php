<?php

declare(strict_types=1);

namespace Chronhub\Projector\Repository;

use Closure;
use Throwable;
use Chronhub\Projector\Status;
use Illuminate\Contracts\Events\Dispatcher;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Support\Events\ProjectorReset;
use Chronhub\Projector\Support\Events\ProjectorFailed;
use Chronhub\Projector\Support\Events\ProjectorDeleted;
use Chronhub\Projector\Support\Events\ProjectorStarted;
use Chronhub\Projector\Support\Events\ProjectorStopped;
use Chronhub\Projector\Support\Events\ProjectorRestarted;

final class EventableRepository implements Repository
{
    public function __construct(private Repository $repository,
                                private Dispatcher $events)
    {
    }

    private function handleProjection(Closure $projection, string $operation): void
    {
        try {
            $projection();
        } catch (Throwable $exception) {
            $this->events->dispatch(
                new ProjectorFailed($this->getStreamName(), $exception, $operation)
            );

            throw $exception;
        }
    }

    public function initiate(): void
    {
        $this->handleProjection(function (): void {
            $this->repository->initiate();

            $this->events->dispatch(new ProjectorStarted($this->getStreamName()));
        }, 'start');
    }

    public function stop(): void
    {
        $this->handleProjection(function (): void {
            $this->repository->stop();

            $this->events->dispatch(new ProjectorStopped($this->getStreamName()));
        }, 'stop');
    }

    public function startAgain(): void
    {
        $this->handleProjection(function (): void {
            $this->repository->startAgain();

            $this->events->dispatch(new ProjectorRestarted($this->getStreamName()));
        }, 'restart');
    }

    public function reset(): void
    {
        $this->handleProjection(function (): void {
            $this->repository->reset();

            $this->events->dispatch(new ProjectorReset($this->getStreamName()));
        }, 'reset');
    }

    public function delete(bool $withEmittedEvents): void
    {
        $this->handleProjection(function () use ($withEmittedEvents): void {
            $this->repository->reset();

            $this->events->dispatch(new ProjectorDeleted($this->getStreamName(), $withEmittedEvents));
        }, 'delete');
    }

    public function loadState(): void
    {
        $this->repository->loadState();
    }

    public function loadStatus(): Status
    {
        return $this->repository->loadStatus();
    }

    public function persist(): void
    {
        $this->repository->persist();
    }

    public function exists(): bool
    {
        return $this->repository->exists();
    }

    public function acquireLock(): void
    {
        $this->repository->acquireLock();
    }

    public function updateLock(): void
    {
        $this->repository->updateLock();
    }

    public function releaseLock(): void
    {
        $this->repository->releaseLock();
    }

    public function getStreamName(): string
    {
        return $this->repository->getStreamName();
    }
}
