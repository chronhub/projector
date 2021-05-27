<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Closure;
use Chronhub\Projector\Context\Context;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Factory\MergeStreamIterator;
use Chronhub\Projector\Factory\StreamEventIterator;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use function array_keys;
use function array_values;

final class HandleStreamEvent
{
    public function __construct(private Chronicler $chronicler,
                                private ?Repository $repository)
    {
    }

    public function __invoke(Context $context, Closure $next): callable|bool
    {
        $streams = $this->retrieveStreams($context);

        $eventHandlers = $context->eventHandlers();

        foreach ($streams as $eventPosition => $event) {
            $context->currentStreamName = $streams->streamName();

            $eventHandled = $eventHandlers($context, $event, $eventPosition, $this->repository);

            if ( ! $eventHandled || $context->runner()->isStopped()) {
                return $next($context);
            }
        }

        return $next($context);
    }

    private function retrieveStreams(Context $context): MergeStreamIterator
    {
        $iterator = [];
        $queryFilter = $context->queryFilter();

        foreach ($context->streamPosition()->all() as $streamName => $position) {
            $queryFilter->setCurrentPosition($position + 1);

            $events = $this->chronicler->retrieveFiltered(
                new StreamName($streamName), $queryFilter
            );

            $iterator[$streamName] = new StreamEventIterator($events);
        }

        return new MergeStreamIterator(array_keys($iterator), ...array_values($iterator));
    }
}
