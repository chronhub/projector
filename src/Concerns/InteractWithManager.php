<?php

declare(strict_types=1);

namespace Chronhub\Projector\Concerns;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\DetectGap;
use Chronhub\Projector\Factory\EventCounter;
use Chronhub\Projector\Factory\DefaultOption;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Projector\Repository\RepositoryLock;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Repository\ReadModelRepository;
use Chronhub\Projector\Repository\ProjectionRepository;
use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use function array_merge;

trait InteractWithManager
{
    protected function createProjectorContext(array $options, ?EventCounter $eventCounter): Context
    {
        $options = $this->createProjectorOption($options);

        $streamPositions = new StreamPosition($this->eventStreamProvider);

        $gapDetector = $eventCounter ? $this->createGapDetector($streamPositions, $options) : null;

        return new Context($options, $this->clock, $streamPositions, $eventCounter, $gapDetector);
    }

    protected function createProjectorOption(array $mergeOptions): Option
    {
        if ($this->options instanceof Option) {
            return $this->options;
        }

        return new DefaultOption(...array_merge($this->options, $mergeOptions));
    }

    protected function createProjectorRepository(Context $context,
                                                 string $streamName,
                                                 ?ReadModel $readModel): Repository
    {
        $repositoryClass = $readModel instanceof ReadModel
            ? ReadModelRepository::class : ProjectionRepository::class;

        return new $repositoryClass(
            $context,
            $this->projectionProvider,
            $this->createRepositoryLock($context->option()),
            $streamName,
            $readModel ?? $this->chronicler
        );
    }

    protected function createGapDetector(StreamPosition $streamPositions, Option $option): DetectGap
    {
        return new DetectGap(
            $streamPositions,
            $this->clock,
            $option->retriesMs(),
            $option->detectionWindows()
        );
    }

    protected function createRepositoryLock(Option $option): RepositoryLock
    {
        return new RepositoryLock(
            $this->clock,
            $option->lockTimoutMs(),
            $option->updateLockThreshold()
        );
    }
}
