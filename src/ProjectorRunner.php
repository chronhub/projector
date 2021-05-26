<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Chronhub\Projector\Exception\ProjectionAlreadyRunning;
use Throwable;
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
        $pipeline = new Pipeline();

        $pipeline->through($this->pipes);

        $exception = null;

        try {
            do {
                $isStopped = $pipeline
                    ->send($context)
                    ->then(fn(Context $context): bool => $context->runner()->isStopped());
            } while ($context->runner()->inBackground() && !$isStopped);
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            if ((!$exception or !$exception instanceof ProjectionAlreadyRunning) && $this->repository) {
                $this->repository->releaseLock();
            }

            if ($exception) {
                throw $exception;
            }
        }
    }
}
