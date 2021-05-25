<?php

declare(strict_types=1);

namespace Chronhub\Projector\Context;

use Chronhub\Projector\Support\Contracts\QueryProjector;

final class ContextualQuery
{
    private ?string $currentStreamName = null;

    public function __construct(private QueryProjector $projector,
                                ?string &$currentStreamName)
    {
        $this->currentStreamName = &$currentStreamName;
    }

    public function stop(): void
    {
        $this->projector->stop();
    }

    public function streamName(): ?string
    {
        return $this->currentStreamName;
    }
}
