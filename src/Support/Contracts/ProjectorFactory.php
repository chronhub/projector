<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Closure;

interface ProjectorFactory extends Projector
{
    public function initialize(Closure $initCallback): ProjectorFactory;

    /**
     * @param string ...$streams
     *
     * @return ProjectorFactory&static
     */
    public function fromStreams(string ...$streams): ProjectorFactory;

    /**
     * @param string ...$categories
     */
    public function fromCategories(string ...$categories): ProjectorFactory;

    public function fromAll(): ProjectorFactory;

    public function when(array $eventHandlers): ProjectorFactory;

    public function whenAny(Closure $eventsHandler): ProjectorFactory;

    /**
     * Run the projection until time is reached
     * time can be in second or a string interval
     * to produce timestamp comparison.
     *
     * @param int|string $delay
     */
    public function until(int|string $delay): ProjectorFactory;

    public function withQueryFilter(ProjectionQueryFilter $queryFilter): ProjectorFactory;
}
