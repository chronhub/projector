<?php

declare(strict_types=1);

namespace Chronhub\Projector\Context;

use Closure;
use Chronhub\Projector\Factory\NoOpTimer;
use Chronhub\Projector\Factory\ProjectorTimer;
use Chronhub\Projector\Factory\ArrayEventHandler;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Factory\ClosureEventHandler;
use Chronhub\Projector\Support\Contracts\Factory\Timer;
use Chronhub\Projector\Support\Contracts\ProjectionQueryFilter;
use function count;

final class ContextFactory
{
    public Closure|null $initCallback = null;
    public Closure|array|null $eventHandlers = null;
    public array $queries = [];
    public ?ProjectionQueryFilter $queryFilter = null;
    public null|Timer $timer = null;

    public function initialize(Closure $initCallback): self
    {
        if (null !== $this->initCallback) {
            throw new RuntimeException('Projection already initialized');
        }

        $this->initCallback = $initCallback;

        return $this;
    }

    public function withQueryFilter(ProjectionQueryFilter $queryFilter): self
    {
        if (null !== $this->queryFilter) {
            throw new RuntimeException('Projection query filter already set');
        }

        $this->queryFilter = $queryFilter;

        return $this;
    }

    public function withTimer(int|string $timer): self
    {
        if (null !== $this->timer) {
            throw new RuntimeException('Projection timer already set');
        }

        $this->timer = new ProjectorTimer($timer);

        return $this;
    }

    public function fromStreams(string ...$streamNames): self
    {
        $this->assertQueriesNotSet();

        $this->queries['names'] = $streamNames;

        return $this;
    }

    public function fromCategories(string ...$categories): self
    {
        $this->assertQueriesNotSet();

        $this->queries['categories'] = $categories;

        return $this;
    }

    public function fromAll(): self
    {
        $this->assertQueriesNotSet();

        $this->queries['all'] = true;

        return $this;
    }

    public function when(array $eventHandlers): self
    {
        $this->assertEventHandlersNotSet();

        $this->eventHandlers = $eventHandlers;

        return $this;
    }

    public function whenAny(Closure $eventHandler): self
    {
        $this->assertEventHandlersNotSet();

        $this->eventHandlers = $eventHandler;

        return $this;
    }

    public function eventHandlers(): callable
    {
        if ($this->eventHandlers instanceof Closure) {
            return new ClosureEventHandler($this->eventHandlers);
        }

        return new ArrayEventHandler($this->eventHandlers);
    }

    public function queries(): array
    {
        return $this->queries;
    }

    public function queryFilter(): ProjectionQueryFilter
    {
        return $this->queryFilter;
    }

    public function timer(): Timer
    {
        return $this->timer ?? new NoOpTimer();
    }

    public function validate(): void
    {
        if (0 === count($this->queries)) {
            throw new RuntimeException('Projection streams all|names|categories not set');
        }

        if (null === $this->eventHandlers) {
            throw new RuntimeException('Projection event handlers not set');
        }

        if (null === $this->queryFilter) {
            throw new RuntimeException('Projection query filter not set');
        }
    }

    public function castEventHandlers(object $contextualEventHandler): void
    {
        if ($this->eventHandlers instanceof Closure) {
            $this->eventHandlers = Closure::bind($this->eventHandlers, $contextualEventHandler);
        } else {
            foreach ($this->eventHandlers as &$handler) {
                $handler = Closure::bind($handler, $contextualEventHandler);
            }
        }
    }

    public function castInitCallback(object $contextualEventHandler): array
    {
        if ($this->initCallback instanceof Closure) {
            $callback = Closure::bind($this->initCallback, $contextualEventHandler);

            $result = $callback();

            $this->initCallback = $callback;

            return $result;
        }

        return [];
    }

    private function assertQueriesNotSet(): void
    {
        if (count($this->queries) > 0) {
            throw new RuntimeException('Projection streams all|names|categories already set');
        }
    }

    private function assertEventHandlersNotSet(): void
    {
        if (null !== $this->eventHandlers) {
            throw new RuntimeException('Projection event handlers already set');
        }
    }
}
