<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Concerns\InteractWithRemoteStatus;

final class PreparePersistentRunner
{
    use InteractWithRemoteStatus;

    private bool $isInitiated = false;

    public function __invoke(Context $context, callable $next): callable|bool
    {
        if ( ! $this->isInitiated) {
            $this->isInitiated = true;

            if ($this->stopOnLoadingRemoteStatus($context->runner()->inBackground())) {
                return true;
            }

            $this->repository->initiate();
        }

        return $next($context);
    }
}
