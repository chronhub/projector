<?php
declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\Projection;

use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;

final class RunProjectionTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_run(): void
    {
        $projector = Project::create('in_memory');

        $projection = $projector->createProjection('customer_stream');
        $projection
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->initialize(fn(): array => ['called' => false])
            ->fromStreams('customer')
            ->whenAny(function (AggregateChanged $event, array $state): array {
                    $state['called'] = true;
                return $state;
            })->run(false);

        $this->assertFalse($projection->getState()['called']);
    }

    protected function defineEnvironment($app)
    {

    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
