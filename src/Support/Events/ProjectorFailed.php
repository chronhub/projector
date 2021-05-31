<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Events;

use Throwable;

final class ProjectorFailed
{
    public function __construct(private string $streamName,
                                private Throwable $exception,
                                private string $operation)
    {
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }
}
