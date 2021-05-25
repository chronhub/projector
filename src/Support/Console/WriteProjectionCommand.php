<?php
declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Projector\Exception\ProjectionNotFound;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Support\Facade\Project;
use Illuminate\Console\Command;

final class WriteProjectionCommand extends Command
{
    protected const DEFAULT_PROJECTOR = 'default';

    private const OPERATIONS_AVAILABLE = ['stop', 'reset', 'delete', 'deleteIncl'];

    protected $signature = 'projector:write
                                {op : operation on projection (available: stop reset delete deleteIncl)}
                                {stream : stream name}
                                {--projector=default}';

    protected $description = 'Stop reset delete ( with/out emitted events ) one projection by stream name';

    private ?Manager $projector;

    public function handle(): void
    {
        [$streamName, $operation] = $this->determineArguments();

        $this->assertProjectionOperationExists($operation);

        if (!$this->confirmOperation($streamName, $operation)) {
            return;
        }

        $this->processProjection($streamName, $operation);

        $this->info("Operation $operation on stream $streamName successful");
    }

    private function processProjection(string $streamName, string $operation): void
    {
        switch ($operation) {
            case 'stop':
                $this->projector()->stop($streamName);
                break;
            case 'reset':
                $this->projector()->reset($streamName);
                break;
            case 'delete':
                $this->projector()->delete($streamName, false);
                break;
            case 'deleteIncl':
                $this->projector()->delete($streamName, true);
                break;
        }
    }

    private function confirmOperation(string $streamName, string $operation): bool
    {
        try {
            $projectionStatus = $this->projector()->statusOf($streamName);
        } catch (ProjectionNotFound) {
            $this->error("Projection not found with stream name $streamName ... operation aborted");

            return false;
        }

        $this->warn("Status of $streamName projection is $projectionStatus");

        if (!$this->confirm("Are you sure you want to $operation stream name $streamName")) {
            $this->warn("Operation $operation on stream $streamName aborted");

            return false;
        }

        return true;
    }

    private function determineArguments(): array
    {
        return [$this->argument('stream'), $this->argument('op')];
    }

    private function assertProjectionOperationExists(string $operation): void
    {
        if (!in_array($operation, self::OPERATIONS_AVAILABLE)) {
            throw new InvalidArgumentException("Invalid operation $operation");
        }
    }

    private function projector(): Manager
    {
        $projectorId = $this->hasOption('projector')
            ? $this->option('projector') : self::DEFAULT_PROJECTOR;

        return $this->projector ?? $this->projector = Project::create($projectorId);
    }
}
