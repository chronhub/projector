<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Scope;

use Chronhub\Foundation\Message\DomainEvent;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Driver\InMemory\InMemoryQueryScope;
use Chronhub\Projector\Support\Contracts\ProjectionQueryScope;
use Chronhub\Projector\Support\Contracts\ProjectionQueryFilter;
use Chronhub\Chronicler\Support\Contracts\Query\InMemoryQueryFilter;

class InMemoryProjectionQueryScope extends InMemoryQueryScope implements ProjectionQueryScope
{
    public function fromIncludedPosition(): ProjectionQueryFilter
    {
        return new class() implements ProjectionQueryFilter, InMemoryQueryFilter {
            private int $currentPosition = 0;

            public function setCurrentPosition(int $position): void
            {
                $this->currentPosition = $position;
            }

            public function filterQuery(): callable
            {
                $position = $this->currentPosition;

                if ($position <= 0) {
                    throw new RuntimeException("Position must be greater than 0, current is $position");
                }

                return function (DomainEvent $event) use ($position): ?DomainEvent {
                    $isGreaterThanPosition = $event->header(Header::INTERNAL_POSITION) >= $position;

                    return $isGreaterThanPosition ? $event : null;
                };
            }

            public function orderBy(): string
            {
                return 'asc';
            }
        };
    }
}
