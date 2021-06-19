<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Traits\InteractWithOption;

class DefaultOption implements Option
{
    use InteractWithOption;

    protected array $retriesMs;

    public function __construct(
        protected bool $dispatchSignal = false,
        protected int $streamCacheSize = 1000,
        protected int $lockTimeoutMs = 1000,
        protected int $sleepBeforeUpdateLock = 10000,
        protected int $sleepWhenStreamNotFound = 100000,
        protected int $persistBlockSize = 1000,
        protected int $updateLockThreshold = 0,
        array|string $retriesMs = [0, 5, 100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 2000, 3000],
        protected string $detectionWindows = 'PT1H')
    {
        $this->setUpRetriesMs($retriesMs);
    }
}
