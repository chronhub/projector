<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Traits;

trait InteractWithOption
{
    public function dispatchSignal(): bool
    {
        return $this->dispatchSignal;
    }

    public function streamCacheSize(): int
    {
        return $this->streamCacheSize;
    }

    public function lockTimoutMs(): int
    {
        return $this->lockTimeoutMs;
    }

    public function sleep(): int
    {
        return $this->sleep;
    }

    public function persistBlockSize(): int
    {
        return $this->persistBlockSize;
    }

    public function updateLockThreshold(): int
    {
        return $this->updateLockThreshold;
    }

    public function retriesMs(): array
    {
        return $this->retriesMs;
    }

    public function detectionWindows(): string
    {
        return $this->detectionWindows;
    }

    public function toArray(): array
    {
        return [
            self::OPTION_DISPATCH_SIGNAL => $this->dispatchSignal(),
            self::OPTION_STREAM_CACHE_SIZE => $this->streamCacheSize(),
            self::OPTION_LOCK_TIMEOUT_MS => $this->lockTimoutMs(),
            self::OPTION_SLEEP => $this->sleep(),
            self::OPTION_UPDATE_LOCK_THRESHOLD => $this->updateLockThreshold(),
            self::OPTION_PERSIST_BLOCK_SIZE => $this->persistBlockSize(),
            self::OPTION_RETRIES_MS => $this->retriesMs(),
            self::OPTION_DETECTION_WINDOWS => $this->detectionWindows(),
        ];
    }
}
