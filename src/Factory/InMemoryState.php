<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Support\Contracts\Factory\State;
use function count;
use function json_encode;

final class InMemoryState implements State
{
    private array $state = [];

    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function resetState(): void
    {
        $this->state = [];
    }

    public function toJson(): string
    {
        if (0 === count($this->state)) {
            return '{}';
        }

        return json_encode($this->getState());
    }
}
