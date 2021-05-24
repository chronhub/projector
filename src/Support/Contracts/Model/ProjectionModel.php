<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts\Model;

interface ProjectionModel
{
    public const TABLE = 'projections';

    public function name(): string;

    public function position(): string;

    public function state(): string;

    public function status(): string;

    public function lockedUntil(): ?string;
}
