<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Events;

final class ProjectorDeleted
{
    public function __construct(private string $streamName, private bool $withEmittedEvents)
    {
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    public function withEmittedEvents(): bool
    {
        return $this->withEmittedEvents;
    }
}
