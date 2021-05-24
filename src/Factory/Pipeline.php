<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Closure;
use Throwable;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Exception\ProjectionAlreadyRunning;

class Pipeline
{
    private array $pipes = [];
    private Context $passable;

    public function __construct(private ?Repository $repository)
    {
    }

    public function send(Context $passable): self
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * @param callable[] $pipes
     *
     * @return $this
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function then(Closure $destination): bool
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    protected function prepareDestination(Closure $destination): Closure
    {
        try {
            return fn (Context $passable) => $destination($passable);
        } catch (Throwable $exception) {
            $this->releaseLockOnException($exception);

            throw $exception;
        }
    }

    protected function carry(): Closure
    {
        try {
            return fn (Closure $stack, callable $pipe) => fn (Context $passable) => $pipe($passable, $stack);
        } catch (Throwable $exception) {
            $this->releaseLockOnException($exception);

            throw $exception;
        }
    }

    protected function releaseLockOnException(Throwable $exception): void
    {
        if ($this->repository && ! $exception instanceof ProjectionAlreadyRunning) {
            $this->repository->releaseLock();
        }
    }
}
