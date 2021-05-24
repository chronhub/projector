<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Illuminate\Support\Collection;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;
use function key;

class StreamPosition
{
    private Collection $container;

    public function __construct(private EventStreamProvider $eventStreamProvider)
    {
        $this->container = new Collection();
    }

    public function watch(array $streamNames): void
    {
        $container = new Collection();

        foreach ($this->loadStreams($streamNames) as $stream) {
            $container[$stream] = 0;
        }

        $this->container = $container->merge($this->container);
    }

    public function discover(array $streamsPositions): void
    {
        $this->container = $this->container->merge($streamsPositions);
    }

    public function bind(string $streamName, int $position): void
    {
        $this->container[$streamName] = $position;
    }

    public function reset(): void
    {
        $this->container = new Collection();
    }

    public function has(string $streamName, int $position): bool
    {
        return $this->container[$streamName] === $position;
    }

    /**
     * @return array<string,int>
     */
    public function toArray(): array
    {
        return $this->container->toArray();
    }

    public function toJson(): string
    {
        if ($this->container->isEmpty()) {
            return '{}';
        }

        return $this->container->toJson();
    }

    /**
     * @return string[]
     */
    protected function loadStreams(array $streamNames): array
    {
        return match (key($streamNames)) {
            'all' => $this->eventStreamProvider->allStreamWithoutInternal(),
            'categories' => $this->eventStreamProvider->filterByCategories($streamNames['categories']),
            default => $this->handleStreamNames($streamNames['names'] ?? [])
        };
    }

    protected function handleStreamNames(array $streamNames): array
    {
        if (0 === count($streamNames)) {
            throw new InvalidArgumentException('Stream names can not be empty');
        }

        return $streamNames;
    }
}
