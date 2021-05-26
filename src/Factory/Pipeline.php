<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Closure;
use Chronhub\Projector\Context\Context;

class Pipeline
{
    private array $pipes = [];
    private Context $passable;

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
        return fn (Context $passable) => $destination($passable);
    }

    protected function carry(): Closure
    {
        return fn (Closure $stack, callable $pipe) => fn (Context $passable) => $pipe($passable, $stack);
    }
}
