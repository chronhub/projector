<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Illuminate\Database\QueryException;
use Chronhub\Projector\Factory\EventCounter;
use Chronhub\Chronicler\Exception\QueryFailure;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Concerns\InteractWithManager;
use Chronhub\Projector\Exception\ProjectionNotFound;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Contracts\ProjectorFactory;
use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use Chronhub\Projector\Support\Contracts\ProjectionQueryScope;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;
use function json_decode;

final class DefaultManager implements Manager
{
    use InteractWithManager;

    public function __construct(protected Chronicler $chronicler,
                                protected EventStreamProvider $eventStreamProvider,
                                protected ProjectionProvider $projectionProvider,
                                protected ProjectionQueryScope $projectionQueryScope,
                                protected Clock $clock,
                                protected Option|array $options = [])
    {
    }

    public function createQuery(array $options = []): ProjectorFactory
    {
        $context = $this->createProjectorContext($options, null);

        return new ProjectQuery($context, $this->chronicler);
    }

    public function createProjection(string $streamName, array $options = []): ProjectorFactory
    {
        $context = $this->createProjectorContext($options, new EventCounter());

        $repository = $this->createProjectorRepository($context, $streamName, null);

        return new ProjectProjection($context, $repository, $this->chronicler, $streamName);
    }

    public function createReadModelProjection(string $streamName, ReadModel $readModel, array $options = []): ProjectorFactory
    {
        $context = $this->createProjectorContext($options, new EventCounter());

        $repository = $this->createProjectorRepository($context, $streamName, $readModel);

        return new ProjectReadModel($context, $repository, $this->chronicler, $streamName, $readModel);
    }

    public function stop(string $streamName): void
    {
        $this->updateProjectionStatus($streamName, Status::STOPPING());
    }

    public function reset(string $streamName): void
    {
        $this->updateProjectionStatus($streamName, Status::RESETTING());
    }

    public function delete(string $streamName, bool $deleteEmittedEvents): void
    {
        $deleteProjectionStatus = $deleteEmittedEvents
            ? Status::DELETING_EMITTED_EVENTS()
            : Status::DELETING();

        $this->updateProjectionStatus($streamName, $deleteProjectionStatus);
    }

    public function statusOf(string $name): string
    {
        $projection = $this->projectionProvider->findByName($name);

        if ( ! $projection instanceof ProjectionModel) {
            throw ProjectionNotFound::withName($name);
        }

        return $projection->status();
    }

    public function streamPositionsOf(string $name): array
    {
        $projection = $this->projectionProvider->findByName($name);

        if ( ! $projection instanceof ProjectionModel) {
            throw ProjectionNotFound::withName($name);
        }

        return json_decode($projection->position(), true);
    }

    public function stateOf(string $name): array
    {
        $projection = $this->projectionProvider->findByName($name);

        if ( ! $projection) {
            throw ProjectionNotFound::withName($name);
        }

        return json_decode($projection->state(), true);
    }

    public function filterNamesOf(string ...$names): array
    {
        return $this->projectionProvider->findByNames(...$names);
    }

    public function exists(string $name): bool
    {
        return $this->projectionProvider->projectionExists($name);
    }

    public function queryScope(): ProjectionQueryScope
    {
        return $this->projectionQueryScope;
    }

    private function updateProjectionStatus(string $streamName, Status $projectionStatus): void
    {
        try {
            $success = $this->projectionProvider->updateProjection(
                $streamName,
                ['status' => $projectionStatus->getValue()]
            );
        } catch (QueryException $exception) {
            throw QueryFailure::fromQueryException($exception);
        }

        if ( ! $success) {
            $this->assertProjectionNameExists($streamName);
        }
    }

    private function assertProjectionNameExists(string $projectionName): void
    {
        if ( ! $this->exists($projectionName)) {
            throw ProjectionNotFound::withName($projectionName);
        }
    }
}
