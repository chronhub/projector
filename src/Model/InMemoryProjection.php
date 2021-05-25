<?php

declare(strict_types=1);

namespace Chronhub\Projector\Model;

use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;

final class InMemoryProjection implements ProjectionModel
{
    private function __construct(private string $name,
                                 private string $position,
                                 private string $state,
                                 private string $status,
                                 private ?string $lockedUntil)
    {
    }

    public static function create(string $name, string $status): self
    {
        return new self($name, '{}', '{}', $status, null);
    }

    public function setPosition(string $position): void
    {
        $this->position = $position;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setLockedUntil(?string $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): string
    {
        return $this->position;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function lockedUntil(): ?string
    {
        return $this->lockedUntil;
    }
}
