<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Chronicler\Support\Contracts\Query\QueryFilter;

interface ProjectionQueryFilter extends QueryFilter
{
    public function setCurrentPosition(int $position): void;
}
