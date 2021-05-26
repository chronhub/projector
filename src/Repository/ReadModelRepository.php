<?php

declare(strict_types=1);

namespace Chronhub\Projector\Repository;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Concerns\InteractWithRepository;
use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;

final class ReadModelRepository implements Repository
{
    use InteractWithRepository;

    public function __construct(protected Context $context,
                                protected ProjectionProvider $provider,
                                protected RepositoryLock $lock,
                                protected string $streamName,
                                private ReadModel $readModel)
    {
    }

    public function initiate(): void
    {
        $this->context->runner()->stop(false);

        if ( ! $this->exists()) {
            $this->createProjection();
        }

        $this->acquireLock();

        if ( ! $this->readModel->isInitialized()) {
            $this->readModel->initialize();
        }

        $this->context->streamPosition()->watch($this->context->queries());

        $this->loadState();
    }

    public function persist(): void
    {
        $this->persistProjection();

        $this->readModel->persist();
    }

    public function reset(): void
    {
        $this->resetProjection();

        $this->readModel->reset();
    }

    public function delete(bool $withEmittedEvents): void
    {
        $this->deleteProjection();

        if ($withEmittedEvents) {
            $this->readModel->down();
        }
    }
}
