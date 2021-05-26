<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Illuminate\Console\Command;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Exception\InvalidArgumentException;

final class ReadProjectionCommand extends Command
{
    protected const DEFAULT_PROJECTOR = 'default';

    protected $signature = 'projector:read
                                {stream : stream name}
                                {field : available field (state position status)}
                                {--projector=default}';

    protected $description = 'Query status, stream position or state by projection stream';

    private Manager $projector;

    public function handle(): void
    {
        $projectorId = $this->option('projector') ?? self::DEFAULT_PROJECTOR;

        $this->projector = Project::create($projectorId);

        [$stream, $fieldName] = $this->determineArguments();

        $result = $this->fetchProjectionByField($stream, $fieldName);
        $result = empty($result) ? 'No result' : json_encode($result);

        $this->info("$fieldName for stream $stream is $result");
    }

    private function fetchProjectionByField(string $streamName, string $fieldName): array
    {
        return match ($fieldName) {
            'state' => $this->projector->stateOf($streamName),
            'position' => $this->projector->streamPositionsOf($streamName),
            'status' => [$this->projector->statusOf($streamName)],
            default => throw new InvalidArgumentException("Invalid field name $fieldName"),
        };
    }

    private function determineArguments(): array
    {
        return [$this->argument('stream'), $this->argument('field')];
    }
}
