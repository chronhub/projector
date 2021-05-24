<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Scope;

use Illuminate\Database\Query\Builder;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Support\Contracts\ProjectionQueryScope;
use Chronhub\Projector\Support\Contracts\ProjectionQueryFilter;
use Chronhub\Chronicler\Driver\Connection\Scope\PgsqlQueryScope;

class PgsqlProjectionQueryScope extends PgsqlQueryScope implements ProjectionQueryScope
{
    public function fromIncludedPosition(): ProjectionQueryFilter
    {
        return new class() implements ProjectionQueryFilter {
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

                return function (Builder $query) use ($position): void {
                    $query
                        ->where('no', '>=', $position)
                        ->orderBy('no');
                };
            }
        };
    }
}
