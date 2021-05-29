<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Illuminate\Support\Collection;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;
use function key;
use function count;

class StreamPosition
{
    private Collection $container;

    public function __construct(private EventStreamProvider $eventStreamProvider)
    {
        $this->container = new Collection();
    }

    public function watch(array $queries): void
    {
        $container = new Collection();

        foreach ($this->loadStreamsFrom($queries) as $stream) {
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

    public function hasNextPosition(string $streamName, int $position): bool
    {
        return $this->container[$streamName] + 1 === $position;
    }

    /**
     * @return array<string,int>
     */
    public function all(): array
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
    protected function loadStreamsFrom(array $queries): array
    {
        return match (key($queries)) {
            'all' => $this->eventStreamProvider->allStreamWithoutInternal(),
            'categories' => $this->eventStreamProvider->filterByCategories($queries['categories']),
            default => $this->handleStreamNames($queries['names'] ?? [])
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
