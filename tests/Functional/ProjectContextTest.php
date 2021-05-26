<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;

final class ProjectContextTest extends TestCaseWithOrchestra
{
    private Manager $projector;

    /**
     * @test
     */
    public function it_raise_exception_when_initialize_state_already_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection already initialized');

        $projection = $this->projector->createProjection('account');
        $projection
            ->initialize(fn (): array => [])
            ->initialize(fn (): array => []);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_query_filter_not_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection query filter not set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->fromStreams('customer')
            ->whenAny(function (): void { })
            ->run(false);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_query_filter_already_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection query filter already set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('customer')
            ->whenAny(function (): void { });
    }

    /**
     * @test
     */
    public function it_raise_exception_when_streams_not_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection streams all|names|categories not set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->whenAny(function (): void { })
            ->run(false);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_streams_already_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection streams all|names|categories already set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->fromStreams('account')
            ->fromStreams('customer');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_event_handlers_not_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection event handlers not set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('customer')
            ->run(false);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_event_handlers_already_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection event handlers already set');

        $projection = $this->projector->createProjection('account');
        $projection
            ->whenAny(function (): void { })
            ->when([]);
    }

    /**
     * @test
     */
    public function it_run_with_timer(): void
    {
        $projection = $this->projector->createProjection('account');

        $projection
            ->until(1)
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('customer')
            ->whenAny(function (): void { })
            ->run(true);

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_timer_already_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projection timer already set');

        $projection = $this->projector->createProjection('account');

        $projection
            ->until(1)
            ->until(5)
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('customer')
            ->whenAny(function (): void { });
    }

    public function defineEnvironment($app): void
    {
        $this->projector = Project::create('in_memory');
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
