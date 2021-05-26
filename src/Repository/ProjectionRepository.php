<?php

declare(strict_types=1);

namespace Chronhub\Projector\Repository;

use Chronhub\Projector\Context\Context;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Chronicler\Exception\StreamNotFound;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Projector\Concerns\InteractWithRepository;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;

final class ProjectionRepository implements Repository
{
    use InteractWithRepository;

    public function __construct(protected Context $context,
                                protected ProjectionProvider $provider,
                                protected RepositoryLock $lock,
                                protected string $streamName,
                                private Chronicler $chronicler)
    {
    }

    public function initiate(): void
    {
        $this->context->runner()->stop(false);

        if ( ! $this->exists()) {
            $this->createProjection();
        }

        $this->acquireLock();

        $this->context->streamPosition()->watch($this->context->streamNames());

        $this->loadState();
    }

    public function persist(): void
    {
        $this->persistProjection();
    }

    public function reset(): void
    {
        // solve the issue when deleting stream which can not be
        // create on emit event as the is stream created is still true
        $this->context->setStreamCreated(false);

        $this->resetProjection();

        $this->deleteStream();
    }

    public function delete(bool $withEmittedEvents): void
    {
        $this->deleteProjection();

        if ($withEmittedEvents) {
            $this->deleteStream();
        }
    }

    private function deleteStream(): void
    {
        try {
            $this->chronicler->delete(new StreamName($this->getStreamName()));
        } catch (StreamNotFound) {
        }
    }
}
