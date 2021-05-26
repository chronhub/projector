<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Illuminate\Console\Command;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Exception\ProjectionNotFound;

abstract class WriteProjectionCommand extends Command
{
    protected const DEFAULT_PROJECTOR = 'default';

    public function handle(): void
    {
        $streamName = new StreamName($this->argument('stream'));

        if ( ! $this->confirmOperation($streamName)) {
            return;
        }

        $this->processProjection($streamName);

        $this->info("Operation {$this->operation()} on $streamName projection successful");
    }

    private function confirmOperation(StreamName $streamName): bool
    {
        try {
            $projectionStatus = $this->projector()->statusOf($streamName->toString());
        } catch (ProjectionNotFound) {
            $this->error("Projection not found with stream $streamName");

            return false;
        }

        $this->warn("Status of $streamName projection is $projectionStatus");

        if ( ! $this->confirm("Are you sure you want to {$this->operation()} stream $streamName")) {
            $this->warn("Operation {$this->operation()} on stream $streamName aborted");

            return false;
        }

        return true;
    }

    protected function projector(): Manager
    {
        $projectorName = $this->hasOption('projector')
            ? $this->option('projector') : self::DEFAULT_PROJECTOR;

        return Project::create($projectorName);
    }

    abstract protected function processProjection(StreamName $streamName): void;

    abstract protected function operation(): string;
}
