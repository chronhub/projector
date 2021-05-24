<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Chronhub\Projector\ProjectorRunner;

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
        return [];
    }
}
