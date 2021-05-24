<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Foundation\Message\DomainEvent;

interface ProjectionProjector extends PersistentProjector
{
    public function emit(DomainEvent $event): void;

    public function linkTo(string $streamName, DomainEvent $event): void;
}
