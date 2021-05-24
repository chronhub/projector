<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Chronhub\Projector\Support\Contracts\Support\ReadModel;

interface ReadModelProjector extends PersistentProjector
{
    public function readModel(): ReadModel;
}
