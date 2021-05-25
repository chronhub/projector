<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Chronhub\Projector\DefaultManager;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryChronicler;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Projector\Support\Option\InMemoryProjectorOption;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;

final class ProjectProjectionTest extends TestCaseWithOrchestra
{
    private Chronicler $chronicler;
    private ProjectionProvider $projectionProvider;
    private EventStreamProvider $eventStreamProvider;
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
            ->whenAny(function (): void {})
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
            ->whenAny(function (): void {});
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
            ->whenAny(function (): void {})
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
            ->whenAny(function (): void {})
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
            ->whenAny(function (): void {})
            ->run(true);

        $this->assertTrue(true);
    }

    public function defineEnvironment($app): void
    {
        $this->setupChronicler($app);

        $this->projector = new DefaultManager(
            $this->chronicler,
            $this->eventStreamProvider,
            $this->projectionProvider,
            new InMemoryProjectionQueryScope(),
            $app->get(Clock::class),
            $app->make(InMemoryProjectorOption::class)
        );
    }

    private function setupChronicler(Application $app): void
    {
        $projectionProvider = $app->make(InMemoryProjectionProvider::class);

        $this->projectionProvider = $app->instance(
            ProjectionProvider::class,
            $projectionProvider
        );

        $eventStreamProvider = $app->make(InMemoryEventStream::class);
        $this->eventStreamProvider = $app->instance(
            EventStreamProvider::class,
            $eventStreamProvider
        );

        $this->chronicler = new InMemoryChronicler($this->eventStreamProvider);
    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
