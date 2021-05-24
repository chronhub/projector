<?php

declare(strict_types=1);

namespace Chronhub\Projector\Repository;

use DateInterval;
use DateTimeImmutable;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use function floor;
use function substr;
use function sprintf;

class RepositoryLock
{
    private ?DateTimeImmutable $lastLock = null;

    public function __construct(private Clock $clock,
                                private int $lockTimeoutMs,
                                private int $lockThreshold)
    {
    }

    public function acquire(): string
    {
        $this->lastLock = $this->clock->fromNow()->dateTime();

        return $this->currentLock();
    }

    public function update(): bool
    {
        $now = $this->clock->fromNow()->dateTime();

        if ($this->shouldUpdateLock($now)) {
            $this->lastLock = $now;

            return true;
        }

        return false;
    }

    public function refresh(): string
    {
        return $this->createLockWithMillisecond(
            $this->clock->fromNow()->dateTime()
        );
    }

    public function currentLock(): string
    {
        return $this->createLockWithMillisecond($this->lastLock);
    }

    public function lastLockUpdate(): ?string
    {
        if ($this->lastLock) {
            return $this->clock->fromDateTime($this->lastLock)->toString();
        }

        return null;
    }

    private function createLockWithMillisecond(DateTimeImmutable $dateTime): string
    {
        $microSeconds = (string) ((int) $dateTime->format('u') + ($this->lockTimeoutMs * 1000));

        $seconds = substr($microSeconds, 0, -6);

        if ('' === $seconds) {
            $seconds = 0;
        }

        return $dateTime
                ->modify('+' . $seconds . ' seconds')
                ->format('Y-m-d\TH:i:s') . '.' . substr($microSeconds, -6);
    }

    private function shouldUpdateLock(DateTimeImmutable $dateTime): bool
    {
        if (null === $this->lastLock || 0 === $this->lockThreshold) {
            return true;
        }

        return $this->incrementLockWithThreshold() <= $dateTime;
    }

    private function incrementLockWithThreshold(): DateTimeImmutable
    {
        $interval = sprintf('PT%sS', floor($this->lockThreshold / 1000));

        $updateLockThreshold = new DateInterval($interval);

        $updateLockThreshold->f = ($this->lockThreshold % 1000) / 1000;

        return $this->lastLock->add($updateLockThreshold);
    }
}
