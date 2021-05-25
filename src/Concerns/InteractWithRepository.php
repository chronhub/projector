<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Chronhub\Projector\Status;
use Chronhub\Projector\Context\Context;
use Illuminate\Database\QueryException;
use Chronhub\Chronicler\Exception\QueryFailure;
use Chronhub\Projector\Repository\RepositoryLock;
use Chronhub\Projector\Exception\ProjectionNotFound;
use Chronhub\Projector\Exception\ProjectionAlreadyRunning;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use function count;
use function is_array;
use function json_decode;

trait InteractWithRepository
{
    protected ProjectionProvider $provider;
    protected Context $context;
    protected RepositoryLock $lock;
    protected string $streamName;

    public function loadState(): void
    {
        $projection = $this->provider->findByName($this->streamName);

        if ( ! $projection instanceof ProjectionModel) {
            throw ProjectionNotFound::withName($this->streamName);
        }

        $this->context->streamPosition()->discover(
            json_decode($projection->position(), true)
        );

        $state = json_decode($projection->state(), true);

        if (is_array($state) && count($state) > 0) {
            $this->context->state()->setState($state);
        }
    }

    public function stop(): void
    {
        $this->persist();

        $this->context->runner()->stop(true);
        $idleProjection = Status::IDLE();

        try {
            $success = $this->provider->updateProjection($this->streamName, [
                'status' => $idleProjection->getValue(),
                //'locked_until' => null,
            ]);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to stop projection for stream name: $this->streamName");
        }

        $this->context->setStatus($idleProjection);
    }

    public function startAgain(): void
    {
        $this->context->runner()->stop(false);
        $runningStatus = Status::RUNNING();

        try {
            $success = $this->provider->updateProjection($this->streamName, [
                'status'       => $runningStatus->getValue(),
                'locked_until' => $this->lock->acquire(),
            ]);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to start projection again for stream name: $this->streamName");
        }

        $this->context->setStatus($runningStatus);
    }

    public function exists(): bool
    {
        return $this->provider->projectionExists($this->streamName);
    }

    public function loadStatus(): Status
    {
        $projection = $this->provider->findByName($this->streamName);

        if ( ! $projection instanceof ProjectionModel) {
            return Status::RUNNING();
        }

        return Status::byValue($projection->status());
    }

    public function acquireLock(): void
    {
        $runningProjection = Status::RUNNING();

        try {
            $success = $this->provider->acquireLock(
                $this->streamName,
                $runningProjection->getValue(),
                $this->lock->acquire(),
                $this->lock->lastLockUpdate(),
            );
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            $message = "Acquiring lock failed for stream $this->streamName: ";
            $message .= 'another projection process is already running or ';
            $message .= 'wait till the stopping process complete';

            throw new ProjectionAlreadyRunning($message);
        }

        $this->context->setStatus($runningProjection);
    }

    public function updateLock(): void
    {
        if ($this->lock->update()) {
            try {
                $success = $this->provider->updateProjection($this->streamName, [
                    'locked_until' => $this->lock->currentLock(),
                    'position'     => $this->context->streamPosition()->toJson(),
                ]);
            } catch (QueryException $queryException) {
                throw QueryFailure::fromQueryException($queryException);
            }

            if ( ! $success) {
                throw new QueryFailure("An error occurred when updating lock for stream name: $this->streamName");
            }
        }
    }

    public function releaseLock(): void
    {
        $idleProjection = Status::IDLE();

        try {
            $this->provider->updateProjection($this->streamName, [
                'status'       => $idleProjection->getValue(),
                'locked_until' => null,
            ]);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        $this->context->setStatus($idleProjection);
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    protected function createProjection(): void
    {
        try {
            $success = $this->provider->createProjection(
                $this->streamName,
                $this->context->status()->getValue()
            );
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to create projection for stream name: $this->streamName");
        }
    }

    protected function persistProjection(): void
    {
        try {
            $success = $this->provider->updateProjection($this->streamName, [
                'position'     => $this->context->streamPosition()->toJson(),
                'state'        => $this->context->state()->toJson(),
                'locked_until' => $this->lock->refresh(),
            ]);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to persist projection for stream name: $this->streamName");
        }
    }

    protected function resetProjection(): void
    {
        $this->context->streamPosition()->reset();

        $this->context->resetStateWithInitialize();

        try {
            $success = $this->provider->updateProjection($this->streamName, [
                'position' => $this->context->streamPosition()->toJson(),
                'state'    => $this->context->state()->toJson(),
                'status'   => $this->context->status()->getValue(),
            ]);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to reset projection for stream name: $this->streamName");
        }
    }

    protected function deleteProjection(): void
    {
        try {
            $success = $this->provider->deleteProjectionByName($this->streamName);
        } catch (QueryException $queryException) {
            throw QueryFailure::fromQueryException($queryException);
        }

        if ( ! $success) {
            throw new QueryFailure("Unable to delete projection for stream name: $this->streamName");
        }

        $this->context->runner()->stop(true);

        $this->context->resetStateWithInitialize();

        $this->context->streamPosition()->reset();
    }
}
