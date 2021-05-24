<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Chronicler\Support\Contracts\Query\QueryScope;

interface ProjectionQueryScope extends QueryScope
{
    public function fromIncludedPosition(): ProjectionQueryFilter;
}
