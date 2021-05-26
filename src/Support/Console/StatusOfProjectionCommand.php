<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class StatusOfProjectionCommand extends ReadProjectionCommand
{
    protected $signature = 'projector:status {stream} {--projector=default}';

    protected $description = 'query status of projection by stream name';

    protected function processProjection(StreamName $streamName): array
    {
        return [$this->projector()->statusOf($streamName->toString())];
    }

    protected function field(): string
    {
        return 'status of';
    }
}
