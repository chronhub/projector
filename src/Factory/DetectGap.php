<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use function count;
use function usleep;
use function array_key_exists;

class DetectGap
{
    private int $retries = 0;
    private bool $gapDetected = false;

    public function __construct(private StreamPosition $streamPosition,
                                private Clock $clock,
                                private array $retriesInMs,
                                private string $detectionWindows)
    {
    }

    public function detect(string $streamName, int $eventPosition, string $eventTime): bool
    {
        if (0 === count($this->retriesInMs)) {
            return false;
        }

        $now = $this->clock->fromNow()->sub($this->detectionWindows);

        if ($now->after($this->clock->fromString($eventTime))) {
            return false;
        }

        if ($this->streamPosition->has($streamName, $eventPosition + 1)) {
            return false;
        }

        return $this->gapDetected = array_key_exists($this->retries, $this->retriesInMs);
    }

    public function hasGap(): bool
    {
        return $this->gapDetected;
    }

    public function sleep(): void
    {
        usleep($this->retriesInMs[$this->retries]);

        ++$this->retries;
    }

    public function resetRetries(): void
    {
        $this->retries = 0;
    }
}
