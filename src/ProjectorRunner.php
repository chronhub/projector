<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\Pipeline;
use Chronhub\Projector\Support\Contracts\Repository;

class ProjectorRunner
{
    public function __construct(private array $pipes,
                                private ?Repository $repository)
    {
    }

    public function __invoke(Context $context): void
    {
        $pipeline = new Pipeline($this->repository);

        $pipeline->through($this->pipes);

        do {
            $isStopped = $pipeline
                ->send($context)
                ->then(fn (Context $context): bool => $context->runner()->isStopped());
        } while ($context->runner()->inBackground() && ! $isStopped);
    }
}
