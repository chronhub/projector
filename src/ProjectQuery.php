<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Pipe\HandleTimer;
use Chronhub\Projector\Pipe\DispatchSignal;
use Chronhub\Projector\Context\ContextFactory;
use Chronhub\Projector\Pipe\HandleStreamEvent;
use Chronhub\Projector\Context\ContextualQuery;
use Chronhub\Projector\Pipe\PrepareQueryRunner;
use Chronhub\Projector\Concerns\InteractWithContext;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Projector\Support\Contracts\QueryProjector;
use Chronhub\Projector\Support\Contracts\ProjectorFactory;

final class ProjectQuery implements QueryProjector, ProjectorFactory
{
    use InteractWithContext;

    public function __construct(protected Context $context,
                                private Chronicler $chronicler)
    {
        $this->factory = new ContextFactory();
    }

    public function run(bool $inBackground): void
    {
        $this->prepareContext($inBackground);

        $run = new ProjectorRunner($this->pipes(), null);

        $run($this->context);
    }

    public function stop(): void
    {
        $this->context->runner()->stop(true);
    }

    public function reset(): void
    {
        $this->context->streamPosition()->reset();

        $this->context->resetStateWithInitialize();
    }

    public function getState(): array
    {
        return $this->context->state()->getState();
    }

    protected function contextualEventHandler(): ContextualQuery
    {
        return new ContextualQuery(
            $this,
            $this->context->clock(),
            $this->context->currentStreamName
        );
    }

    private function pipes(): array
    {
        return [
            new HandleTimer($this),
            new PrepareQueryRunner(),
            new HandleStreamEvent($this->chronicler, null),
            new DispatchSignal(),
        ];
    }
}
