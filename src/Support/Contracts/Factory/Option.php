<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts\Factory;

interface Option
{
    public const OPTION_DISPATCH_SIGNAL = 'dispatchSignal';
    public const OPTION_STREAM_CACHE_SIZE = 'streamCacheSize';
    public const OPTION_SLEEP_BEFORE_UPDATE_LOCK = 'sleepBeforeUpdateLock';
    public const OPTION_PERSIST_BLOCK_SIZE = 'persistBlockSize';
    public const OPTION_LOCK_TIMEOUT_MS = 'lockTimeoutMs';
    public const OPTION_UPDATE_LOCK_THRESHOLD = 'updateLockThreshold';
    public const OPTION_RETRIES_MS = 'retriesMs';
    public const OPTION_DETECTION_WINDOWS = 'detectionWindows';

    public function dispatchSignal(): bool;

    public function streamCacheSize(): int;

    public function lockTimoutMs(): int;

    public function sleepBeforeUpdateLock(): int;

    public function persistBlockSize(): int;

    public function updateLockThreshold(): int;

    public function retriesMs(): array;

    /**
     * Avoid gap retries during projection by comparing
     * current time with detection windows subtracted
     * greater than the time of recording message.
     *
     * Gaps for replaying projection are issued by transaction rollbacks
     */
    public function detectionWindows(): string;

    public function toArray(): array;
}
