<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;

final class PrepareQueryRunner
{
    private bool $isInitiated = false;

    public function __invoke(Context $context, callable $next): callable|bool
    {
        if ( ! $this->isInitiated) {
            $this->isInitiated = true;

            $context->position()->watch($context->streamNames());
        }

        return $next($context);
    }
}
