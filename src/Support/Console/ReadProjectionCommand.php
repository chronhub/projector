<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Illuminate\Console\Command;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Support\Contracts\Manager;
use function json_encode;

abstract class ReadProjectionCommand extends Command
{
    protected const DEFAULT_PROJECTOR = 'default';

    public function handle(): void
    {
        $stream = new StreamName($this->argument('stream'));

        $result = $this->processProjection($stream);

        $result = empty($result) ? 'No result' : json_encode($result);

        $this->info("{$this->field()} $stream projection is: ");

        $this->info($result);
    }

    protected function projector(): Manager
    {
        $projectorName = $this->hasOption('projector')
            ? $this->option('projector') : self::DEFAULT_PROJECTOR;

        return Project::create($projectorName);
    }

    abstract protected function processProjection(StreamName $streamName): array;

    abstract protected function field(): string;
}
