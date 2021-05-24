<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Iterator;
use Countable;
use Chronhub\Projector\Support\Traits\HasTimSort;

class MergeStreamIterator implements Iterator, Countable
{
    use HasTimSort;

    private array $iterators = [];
    private int $numberOfIterators;
    private array $originalIteratorOrder;

    public function __construct(array $streamNames, StreamEventIterator ...$iterators)
    {
        foreach ($iterators as $key => $iterator) {
            $this->iterators[$key][0] = $iterator;
            $this->iterators[$key][1] = $streamNames[$key];
        }

        $this->numberOfIterators = count($this->iterators);
        $this->originalIteratorOrder = $this->iterators;

        $this->prioritizeIterators();
    }

    public function rewind(): void
    {
        foreach ($this->iterators as $iterator) {
            $iterator[0]->rewind();
        }

        $this->prioritizeIterators();
    }

    public function valid(): bool
    {
        foreach ($this->iterators as $iterator) {
            if ($iterator[0]->valid()) {
                return true;
            }
        }

        return false;
    }

    public function next(): void
    {
        // only advance the prioritized iterator
        $this->iterators[0][0]->next();

        $this->prioritizeIterators();
    }

    public function current()
    {
        return $this->iterators[0][0]->current();
    }

    public function streamName(): string
    {
        return $this->iterators[0][1];
    }

    public function key(): int
    {
        return $this->iterators[0][0]->key();
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->iterators as $iterator) {
            $count += count($iterator[0]);
        }

        return $count;
    }

    protected function prioritizeIterators(): void
    {
        if ($this->numberOfIterators > 1) {
            $this->iterators = $this->originalIteratorOrder;

            $this->timSort($this->iterators, $this->numberOfIterators);
        }
    }
}
