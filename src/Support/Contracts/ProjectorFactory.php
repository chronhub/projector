<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

use Closure;
use Chronhub\Chronicler\Support\Contracts\Query\QueryFilter;

interface ProjectorFactory extends Projector
{
    /**
     * @return ProjectorFactory&static
     */
    public function initialize(Closure $initCallback): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function fromStreams(string ...$streams): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function fromCategories(string ...$categories): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function fromAll(): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function when(array $eventHandlers): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function whenAny(Closure $eventsHandler): ProjectorFactory;

    /**
     * Run the projection until time is reached
     * delay can be in seconds or a string interval
     * to produce timestamp comparison.
     *
     * note that projection should stop gracefully and
     * could not exactly stopped at the delay requested
     *
     * @return ProjectorFactory&static
     */
    public function until(int|string $delay): ProjectorFactory;

    /**
     * @return ProjectorFactory&static
     */
    public function withQueryFilter(QueryFilter $queryFilter): ProjectorFactory;
}
