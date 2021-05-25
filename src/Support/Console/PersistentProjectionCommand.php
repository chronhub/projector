<?php
declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Chronhub\Projector\Support\Contracts\ProjectionQueryFilter;
use Chronhub\Projector\Support\Contracts\ProjectorFactory;
use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use Chronhub\Projector\Support\Facade\Project;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;

abstract class PersistentProjectionCommand extends Command implements SignalableCommandInterface
{
    protected const DEFAULT_PROJECTOR = 'default';
    protected const DISPATCH_SIGNAL = false;

    protected ?ProjectorFactory $projector = null;

    protected function withProjection(string $streamName,
                                      string|ReadModel $readModel = null,
                                      array $options = [],
                                      ?ProjectionQueryFilter $queryFilter = null): void
    {
        if ($this->dispatchSignal()) {
            pcntl_async_signals(true);
        }

        $this->projector = $readModel
            ? $this->projectReadModel($streamName, $readModel, $options, $queryFilter)
            : $this->projectPersistentProjection($streamName, $options, $queryFilter);
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT];
    }

    public function handleSignal(int $signal): void
    {
        if ($this->dispatchSignal()) {
            $this->line('Stopping projection ...');

            $this->projector->stop();
        }
    }

    protected function projectorName(): string
    {
        if ($this->hasOption('projector')) {
            return $this->option('projector');
        }

        return self::DEFAULT_PROJECTOR;
    }

    protected function dispatchSignal(): bool
    {
        if ($this->hasOption('signal')) {
            return (int)$this->option('signal') === 1;
        }

        return self::DISPATCH_SIGNAL;
    }

    private function projectReadModel(string $streamName,
                                      string|ReadModel $readModel,
                                      array $options = [],
                                      ?ProjectionQueryFilter $queryFilter = null): ProjectorFactory
    {
        if (is_string($readModel)) {
            $readModel = $this->getLaravel()->make($readModel);
        }

        $projector = Project::create($this->projectorName());

        $queryFilter = $queryFilter ?? $projector->queryScope()->fromIncludedPosition();

        return $projector
            ->createReadModelProjection($streamName, $readModel, $options)
            ->withQueryFilter($queryFilter);
    }

    private function projectPersistentProjection(string $streamName,
                                                 array $options = [],
                                                 ?ProjectionQueryFilter $queryFilter = null): ProjectorFactory
    {
        $projector = Project::create($this->projectorName());

        $queryFilter = $queryFilter ?? $projector->queryScope()->fromIncludedPosition();

        return $projector
            ->createProjection($streamName, $options)
            ->withQueryFilter($queryFilter);
    }
}
