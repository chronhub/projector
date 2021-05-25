<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Chronhub\Projector\DefaultManager;
use Illuminate\Contracts\Config\Repository;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Support\Contracts\ServiceManager;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;

final class ServiceManagerTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_create_projector_instance(): void
    {
        $manager = $this->app[ServiceManager::class]->create('in_memory');

        $this->assertInstanceOf(DefaultManager::class, $manager);
    }

    /**
     * @test
     */
    public function it_can_be_extended(): void
    {
        $this->app['config']->set('projector.projectors.in_memory_extended', []);

        $instance = new DefaultManager(
            Chronicle::create('in_memory'),
            $this->app->make(InMemoryEventStream::class),
            $this->app->make(InMemoryProjectionProvider::class),
            new InMemoryProjectionQueryScope(),
            $this->app->make(Clock::class),
            []
        );

        $this->app[ServiceManager::class]->extends(
            'in_memory_extended',
            function (Application $app, array $config) use ($instance): Manager {
                $this->assertEquals($config, $app[Repository::class]->get('projector'));

                return $instance;
            });

        $this->assertEquals($instance, $this->app[ServiceManager::class]->create('in_memory_extended'));
        $this->assertNotEquals($instance, $this->app[ServiceManager::class]->create('in_memory'));
    }

    /**
     * @test
     */
    public function it_test_facade(): void
    {
        $manager = $this->app[ServiceManager::class]->create('in_memory');

        $sameInstance = Project::create('in_memory');

        $this->assertEquals($manager, $sameInstance);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_projector_name_not_found(): void
    {
        $this->app[Repository::class]->set('projector.projectors', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration for projector manager invalid_name');

        $this->app[ServiceManager::class]->create('invalid_name');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_projector_provider_not_found(): void
    {
        $this->app[Repository::class]->set('projector.provider', 'not_found');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to determine projection provider with key in_memory');

        $this->app[ServiceManager::class]->create('in_memory');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_event_stream_provider_no_registered_in_container(): void
    {
        $this->app[Repository::class]->set('projector.provider', 'not_found');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to determine projection provider with key in_memory');

        $this->app[ServiceManager::class]->create('in_memory');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_event_stream_provider_not_found(): void
    {
        $this->markTestIncomplete('Chronicler manager should raise first the "same" exception');
    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->singleton(InMemoryEventStream::class);
    }
}
