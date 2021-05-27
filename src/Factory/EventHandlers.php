<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Status;
use Chronhub\Projector\Context\Context;
use Chronhub\Foundation\Message\DomainEvent;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use function in_array;
use function pcntl_signal_dispatch;

abstract class EventHandlers
{
    protected function preProcess(Context $context,
                                  DomainEvent $event,
                                  int $position,
                                  ?Repository $repository): bool
    {
        if ($context->option()->dispatchSignal()) {
            pcntl_signal_dispatch();
        }

        $streamName = $context->currentStreamName;

        if ($repository) {
            $timeOfRecording = $event->header(Header::EVENT_TIME);

            if ($context->gap()->detect($streamName, $position, $timeOfRecording)) {
                return false;
            }
        }

        $context->streamPosition()->bind($streamName, $position);

        if ($repository) {
            $context->eventCounter()->increment();
        }

        return true;
    }

    protected function afterProcess(Context $context, ?array $state, ?Repository $repository): bool
    {
        if ($state) {
            $context->state()->setState($state);
        }

        if ($repository) {
            $this->persistOnReachedCounter($context, $repository);
        }

        return ! $context->runner()->isStopped();
    }

    protected function persistOnReachedCounter(Context $context, Repository $repository): void
    {
        $persistBlockSize = $context->option()->persistBlockSize();

        if ($context->eventCounter()->equals($persistBlockSize)) {
            $repository->persist();

            $context->eventCounter()->reset();

            $context->setStatus($repository->loadStatus());

            $keepProjectionRunning = [Status::RUNNING(), Status::IDLE()];

            if ( ! in_array($context->status(), $keepProjectionRunning)) {
                $context->runner()->stop(true);
            }
        }
    }
}
