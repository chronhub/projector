<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Closure;
use Chronhub\Foundation\Aggregate\AggregateChanged;

final class ProjectAllStreamCommand extends PersistentProjectionCommand
{
    protected $signature = 'projector:all_stream {--projector=default} {--signal=1}';

    protected $description = 'optimize queries by projecting all events in one table';

    public function handle(): void
    {
        $this->withProjection('$all');

        $this->projector
            ->fromAll()
            ->whenAny($this->eventHandler())
            ->run(true);
    }

    private function eventHandler(): Closure
    {
        return function (AggregateChanged $event): void {
            $this->emit($event);
        };
    }
}
