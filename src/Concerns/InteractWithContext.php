<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Closure;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Context\ContextFactory;
use Chronhub\Projector\Support\Contracts\ProjectorFactory;
use Chronhub\Chronicler\Support\Contracts\Query\QueryFilter;

trait InteractWithContext
{
    protected Context $context;
    protected ContextFactory $factory;

    /**
     * @return ProjectorFactory&static
     */
    public function initialize(Closure $initCallback): ProjectorFactory
    {
        $this->factory->initialize($initCallback);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function fromStreams(string ...$streams): ProjectorFactory
    {
        $this->factory->fromStreams(...$streams);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function fromCategories(string ...$categories): ProjectorFactory
    {
        $this->factory->fromCategories(...$categories);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function fromAll(): ProjectorFactory
    {
        $this->factory->fromAll();

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function when(array $eventHandlers): ProjectorFactory
    {
        $this->factory->when($eventHandlers);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function whenAny(Closure $eventsHandler): ProjectorFactory
    {
        $this->factory->whenAny($eventsHandler);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function until(int|string $delay): ProjectorFactory
    {
        $this->factory->withTimer($delay);

        return $this;
    }

    /**
     * @return ProjectorFactory&static
     */
    public function withQueryFilter(QueryFilter $queryFilter): ProjectorFactory
    {
        $this->factory->withQueryFilter($queryFilter);

        return $this;
    }

    protected function prepareContext(bool $inBackground): void
    {
        $this->context->withFactory($this->factory);

        $this->context->runner()->runInBackground($inBackground);

        $this->context->cast($this->contextualEventHandler());
    }
}
