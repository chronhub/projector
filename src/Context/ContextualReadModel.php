<?php

declare(strict_types=1);

namespace Chronhub\Projector\Context;

use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use Chronhub\Projector\Support\Contracts\ReadModelProjector;

final class ContextualReadModel
{
    private ?string $currentStreamName;

    public function __construct(private ReadModelProjector $projector,
                                ?string &$currentStreamName)
    {
        $this->currentStreamName = &$currentStreamName;
    }

    public function stop(): void
    {
        $this->projector->stop();
    }

    public function readModel(): ReadModel
    {
        return $this->projector->readModel();
    }

    public function streamName(): ?string
    {
        return $this->currentStreamName;
    }
}
