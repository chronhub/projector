<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Support\Contracts\Projector;

final class HandleTimer
{
    public function __construct(private Projector $projector)
    {
    }

    public function __invoke(Context $context, callable $next): callable|bool
    {
        $context->timer()->start();

        $process = $next($context);

        if ( ! $context->runner()->isStopped() && $context->timer()->isExpired()) {
            $this->projector
                ? $this->projector->stop()
                : $context->runner()->stop(true);
        }

        return $process;
    }
}
