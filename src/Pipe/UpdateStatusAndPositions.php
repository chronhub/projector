<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Concerns\InteractWithRemoteStatus;

final class UpdateStatusAndPositions
{
    use InteractWithRemoteStatus;

    public function __invoke(Context $context, callable $next): callable|bool
    {
        $this->reloadRemoteStatus($context->runner()->inBackground());

        $context->streamPosition()->watch($context->streamNames());

        return $next($context);
    }
}
