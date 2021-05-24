<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Context\ContextFactory;
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

    private function pipes(): array
    {
        return [];
    }
}
