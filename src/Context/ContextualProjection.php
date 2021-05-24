<?php

declare(strict_types=1);

namespace Chronhub\Projector\Context;

use Chronhub\Foundation\Message\DomainEvent;
use Chronhub\Projector\Support\Contracts\ProjectionProjector;

final class ContextualProjection
{
    private ?string $currentStreamName;

    public function __construct(private ProjectionProjector $projector,
                                ?string &$currentStreamName)
    {
        $this->currentStreamName = &$currentStreamName;
    }

    public function stop(): void
    {
        $this->projector->stop();
    }

    public function linkTo(string $streamName, DomainEvent $event): void
    {
        $this->projector->linkTo($streamName, $event);
    }

    public function emit(DomainEvent $event): void
    {
        $this->projector->emit($event);
    }

    public function streamName(): ?string
    {
        return $this->currentStreamName;
    }
}
