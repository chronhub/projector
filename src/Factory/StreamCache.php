<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Exception\InvalidArgumentException;
use function in_array;
use function array_fill;

class StreamCache
{
    private array $container;
    private int $position = -1;
    private int $size;

    public function __construct(int $size)
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Stream cache size must be greater than 0');
        }

        $this->size = $size;
        $this->container = array_fill(0, $size, null);
    }

    public function push(string $streamName): void
    {
        $this->position = ++$this->position % $this->size;

        $this->container[$this->position] = $streamName;
    }

    public function has(string $streamName): bool
    {
        return in_array($streamName, $this->container, true);
    }

    public function all(): array
    {
        return $this->container;
    }
}
