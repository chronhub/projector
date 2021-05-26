<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class DeleteProjectionCommand extends WriteProjectionCommand
{
    protected $signature = 'projector:delete {stream} {--projector=default}';

    protected $description = 'delete projection by stream name';

    protected function processProjection(StreamName $streamName): void
    {
        $this->projector()->delete($streamName->toString(), false);
    }

    protected function operation(): string
    {
        return 'delete';
    }
}
