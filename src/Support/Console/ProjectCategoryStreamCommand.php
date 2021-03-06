<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Console;

use Closure;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use function strpos;
use function substr;

final class ProjectCategoryStreamCommand extends PersistentProjectionCommand
{
    protected $signature = 'projector:category {--projector=default} {--signal=1}';

    protected $description = 'optimize queries by projecting events per categories';

    public function handle(): void
    {
        $this->withProjection('$by_category');

        $this->projector
            ->fromAll()
            ->whenAny($this->eventHandler())
            ->run(true);
    }

    private function eventHandler(): Closure
    {
        return function (AggregateChanged $event): void {
            $streamName = $this->streamName();

            $pos = strpos($streamName, '-');

            if (false === $pos) {
                return;
            }

            $category = substr($streamName, 0, $pos);

            $this->linkTo('$ct-' . $category, $event);
        };
    }
}
