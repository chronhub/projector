<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;

final class ResetEventCounter
{
    public function __invoke(Context $context, callable $next): callable|bool
    {
        $context->eventCounter()->reset();

        return $next($context);
    }
}
