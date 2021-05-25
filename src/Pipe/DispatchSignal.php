<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;

final class DispatchSignal
{
    public function __invoke(Context $context, callable $next): callable|bool
    {
        if ($context->option()->dispatchSignal()) {
            pcntl_signal_dispatch();
        }

        return $next($context);
    }
}
