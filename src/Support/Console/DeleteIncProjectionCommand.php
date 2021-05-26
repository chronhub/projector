<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Chronicler\Stream\StreamName;

final class DeleteIncProjectionCommand extends WriteProjectionCommand
{
    protected $signature = 'projector:deleteIncl {stream} {--projector=default}';

    protected $description = 'delete with emitted events projection by stream name';

    protected function processProjection(StreamName $streamName): void
    {
        $this->projector()->delete($streamName->toString(), true);
    }

    protected function operation(): string
    {
        return 'delete with emitted events';
    }
}
