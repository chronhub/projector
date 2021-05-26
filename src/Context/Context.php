<?php

declare(strict_types=1);

namespace Chronhub\Projector\Context;

use Closure;
use Chronhub\Projector\Status;
use Chronhub\Projector\RunnerController;
use Chronhub\Projector\Factory\DetectGap;
use Chronhub\Projector\Factory\EventCounter;
use Chronhub\Projector\Factory\InMemoryState;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Support\Contracts\Factory\State;
use Chronhub\Projector\Support\Contracts\Factory\Timer;
use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Contracts\ProjectionQueryFilter;
use function is_array;

/**
 * @method array                 queries()
 * @method callable              eventHandlers()
 * @method ProjectionQueryFilter queryFilter()
 * @method Timer                 timer()
 */
class Context
{
    public ?string $currentStreamName = null;
    private bool $isStreamCreated = false;
    private State $state;
    private Status $status;
    private RunnerController $runner;
    private ContextFactory $factory;

    public function __construct(private Option $option,
                                private Clock $clock,
                                private StreamPosition $streamPosition,
                                private ?EventCounter $eventCounter,
                                private ?DetectGap $gap)
    {
        $this->state = new InMemoryState();
        $this->status = Status::IDLE();
        $this->runner = new RunnerController();
    }

    public function cast(object $contextualHandler): void
    {
        $initState = $this->factory->castInitCallback($contextualHandler);

        $this->state->setState($initState);

        $this->factory->castEventHandlers($contextualHandler);
    }

    public function resetStateWithInitialize(): void
    {
        $this->state->resetState();

        $callback = $this->factory->initCallback;

        if ($callback instanceof Closure) {
            $state = $callback();

            if (is_array($state)) {
                $this->state->setState($state);
            }
        }
    }

    public function withFactory(ContextFactory $factory): void
    {
        $factory->validate();

        $this->factory = $factory;
    }

    public function runner(): RunnerController
    {
        return $this->runner;
    }

    public function state(): State
    {
        return $this->state;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    public function streamPosition(): StreamPosition
    {
        return $this->streamPosition;
    }

    public function eventCounter(): EventCounter
    {
        return $this->eventCounter;
    }

    public function gap(): DetectGap
    {
        return $this->gap;
    }

    public function option(): Option
    {
        return $this->option;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function isStreamCreated(): bool
    {
        return $this->isStreamCreated;
    }

    public function setStreamCreated(bool $isStreamCreated): void
    {
        $this->isStreamCreated = $isStreamCreated;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return call_user_func_array([$this->factory, $method], $arguments);
    }
}
