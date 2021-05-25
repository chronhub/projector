<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Option;

use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Traits\InteractWithOption;

final class InMemoryProjectorOption implements Option
{
    use InteractWithOption;
    private bool $dispatchSignal = false;
    private int $streamCacheSize = 1000;
    private int $lockTimeoutMs = 0;
    private int $sleep = 1;
    private int $persistBlockSize = 1;
    private int $updateLockThreshold = 0;
    private array $retriesMs = [];
    private string $detectionWindows = 'PT10S';
}
