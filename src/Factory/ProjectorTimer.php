<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use Chronhub\Projector\Support\Contracts\Factory\Timer;
use Chronhub\Projector\Exception\InvalidArgumentException;
use function is_int;

final class ProjectorTimer implements Timer
{
    private ?int $endAt = null;

    public function __construct(private int|string $timer)
    {
        if (is_int($timer) && $timer < 1) {
            throw new InvalidArgumentException("Integer projector timer must be greater than zero, current is $timer");
        }
    }

    public function start(): void
    {
        if (null === $this->endAt) {
            $now = $this->now();

            $this->endAt = $this->determineTimer($now);
        }
    }

    public function isExpired(): bool
    {
        return $this->now()->getTimestamp() >= $this->endAt;
    }

    private function determineTimer(DateTimeImmutable $datetime): int
    {
        if (is_int($this->timer)) {
            return $datetime->getTimestamp() + $this->timer;
        }

        return $datetime->add(new DateInterval($this->timer))->getTimestamp();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
