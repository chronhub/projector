<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Support\Contracts\PersistentProjector;

final class StopWhenRunningOnce
{
    public function __construct(private PersistentProjector $projector)
    {
    }

    public function __invoke(Context $context, callable $next): callable|bool
    {
        if ( ! $context->runner()->inBackground() && ! $context->runner()->isStopped()) {
            $this->projector->stop();
        }

        return $next($context);
    }
}
