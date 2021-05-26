<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class StreamPositionOfProjectionCommand extends ReadProjectionCommand
{
    protected $signature = 'projector:position {stream} {--projector=default}';

    protected $description = 'query stream position of projection by stream name';

    protected function processProjection(StreamName $streamName): array
    {
        return $this->projector()->streamPositionsOf($streamName->toString());
    }

    protected function field(): string
    {
        return 'stream position of';
    }
}
