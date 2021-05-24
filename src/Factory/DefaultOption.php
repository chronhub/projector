<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Traits\InteractWithOption;

class DefaultOption implements Option
{
    use InteractWithOption;

    public function __construct(
        private bool $dispatchSignal = false,
        private int $streamCacheSize = 1000,
        private int $lockTimeoutMs = 1000,
        private int $sleep = 10000,
        private int $persistBlockSize = 1000,
        private int $updateLockThreshold = 0,
        private array $retriesMs = [0, 5, 100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 2000, 3000],
        private string $detectionWindows = 'PT60S')
    {
    }
}
