<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class StateOfProjectionCommand extends ReadProjectionCommand
{
    protected $signature = 'projector:state {stream} {--projector=default}';

    protected $description = 'query stream state of projection by stream name';

    protected function processProjection(StreamName $streamName): array
    {
        return $this->projector()->stateOf($streamName->toString());
    }

    protected function field(): string
    {
        return 'state of';
    }
}
