<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Chronhub\Projector\Pipe\HandleGap;
use Chronhub\Projector\ProjectorRunner;
use Chronhub\Projector\Pipe\HandleTimer;
use Chronhub\Projector\Pipe\DispatchSignal;
use Chronhub\Projector\Pipe\HandleStreamEvent;
use Chronhub\Projector\Pipe\ResetEventCounter;
use Chronhub\Projector\Pipe\PersistOrUpdateLock;
use Chronhub\Projector\Pipe\StopWhenRunningOnce;
use Chronhub\Projector\Pipe\PreparePersistentRunner;
use Chronhub\Projector\Pipe\UpdateStatusAndPositions;
use Chronhub\Projector\Support\Contracts\PersistentProjector;

trait InteractWithPersistentProjector
{
    public function run(bool $inBackground): void
    {
        $this->prepareContext($inBackground);

        $run = new ProjectorRunner($this->pipes(), $this->repository);

        $run($this->context);
    }

    public function stop(): void
    {
        $this->repository->stop();
    }

    public function reset(): void
    {
        $this->repository->reset();
    }

    public function delete(bool $withEmittedEvents): void
    {
        $this->repository->delete($withEmittedEvents);
    }

    public function getState(): array
    {
        return $this->context->state()->getState();
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    protected function pipes(): array
    {
        /* @var PersistentProjector $this */

        return [
            new HandleTimer($this),
            new PreparePersistentRunner($this->repository),
            new HandleStreamEvent($this->chronicler, $this->repository),
            new PersistOrUpdateLock($this->repository),
            new HandleGap($this->repository),
            new ResetEventCounter(),
            new DispatchSignal(),
            new UpdateStatusAndPositions($this->repository),
            new StopWhenRunningOnce($this),
        ];
    }
}
