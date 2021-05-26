<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Chronhub\Projector\Model\Projection;
use Illuminate\Contracts\Config\Repository;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Support\Contracts\ServiceManager;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Support\Console\ReadProjectionCommand;
use Chronhub\Projector\Support\Console\WriteProjectionCommand;
use Chronhub\Projector\Support\Option\InMemoryProjectorOption;
use Chronhub\Projector\Support\Console\ProjectAllStreamCommand;
use Chronhub\Projector\Support\Scope\PgsqlProjectionQueryScope;
use Chronhub\Projector\Support\Console\ProjectMessageNameCommand;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Projector\Support\Console\ProjectCategoryStreamCommand;

final class ProjectorServiceProviderTest extends TestCaseWithOrchestra
{
    protected function getPackageProviders($app)
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

    /**
     * @test
     */
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(ServiceManager::class));
        $this->assertTrue($this->app->bound(Project::SERVICE_NAME));
    }

    /**
     * @test
     */
    public function it_fix_deferrable_services(): void
    {
        $this->assertEquals([
            ServiceManager::class,
            Project::SERVICE_NAME,
        ], $this->app->getProvider(ProjectorServiceProvider::class)->provides());
    }

    /**
     * @test
     */
    public function it_fix_projector_configuration(): void
    {
        $config = $this->app[Repository::class]->get('projector');

        $this->assertEquals($config, [
            'provider' => [
                'eloquent' => Projection::class,
                'in_memory' => InMemoryProjectionProvider::class,
            ],
            'projectors' => [
                'default' => [
                    'chronicler'                => 'pgsql',
                    'options'                   => 'lazy',
                    'provider'                  => 'eloquent',
                    'event_stream_provider'     => 'eloquent',
                    'dispatch_projector_events' => true,
                    'scope'                     => PgsqlProjectionQueryScope::class,
                ],
                'in_memory' => [
                    'chronicler'            => 'in_memory',
                    'options'               => 'in_memory',
                    'provider'              => 'in_memory',
                    'event_stream_provider' => 'in_memory',
                    'scope'                 => InMemoryProjectionQueryScope::class,
                ],
            ],
            'options'    => [
                'default' => [],
                'in_memory' => InMemoryProjectorOption::class,
                'snapshot' => [],
            ],
            'console'    => [
                'load_migrations' => true,
                'load_commands' => true,
                'commands' => [
                    ReadProjectionCommand::class,
                    WriteProjectionCommand::class,
                    ProjectAllStreamCommand::class,
                    ProjectCategoryStreamCommand::class,
                    ProjectMessageNameCommand::class,
                ],
            ],
        ]);
    }
}
