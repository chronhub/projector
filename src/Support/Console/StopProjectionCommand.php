<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class StopProjectionCommand extends WriteProjectionCommand
{
    protected $signature = 'projector:stop {stream} {--projector=default}';

    protected $description = 'stop projection by stream name';

    protected function processProjection(StreamName $streamName): void
    {
        $this->projector()->stop($streamName->toString());
    }

    protected function operation(): string
    {
        return 'stop';
    }
}
