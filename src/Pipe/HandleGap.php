<?php

declare(strict_types=1);

namespace Chronhub\Projector\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Support\Contracts\Repository;

final class HandleGap
{
    public function __construct(private Repository $repository)
    {
    }

    public function __invoke(Context $context, callable $next): callable|bool
    {
        $context->gap()->hasGap()
            ? $this->persistProjection($context)
            : $context->gap()->resetRetries();

        return $next($context);
    }

    private function persistProjection(Context $context): void
    {
        $context->gap()->sleep();

        $this->repository->persist();

        $context->gap()->resetGap();
    }
}
