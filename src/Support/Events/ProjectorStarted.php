<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Events;

final class ProjectorStarted
{
    public function __construct(private string $streamName)
    {
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }
}
