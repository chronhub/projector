<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Chronhub\Projector\Model\Projection;
use Illuminate\Contracts\Config\Repository;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Contracts\ServiceManager;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Support\Console\StopProjectionCommand;
use Chronhub\Projector\Support\Console\ResetProjectionCommand;
use Chronhub\Projector\Support\Option\InMemoryProjectorOption;
use Chronhub\Projector\Support\Console\DeleteProjectionCommand;
use Chronhub\Projector\Support\Console\ProjectAllStreamCommand;
use Chronhub\Projector\Support\Scope\PgsqlProjectionQueryScope;
use Chronhub\Projector\Support\Console\StateOfProjectionCommand;
use Chronhub\Projector\Support\Console\ProjectMessageNameCommand;
use Chronhub\Projector\Support\Console\StatusOfProjectionCommand;
use Chronhub\Projector\Support\Console\DeleteIncProjectionCommand;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Projector\Support\Console\ProjectCategoryStreamCommand;
use Chronhub\Projector\Support\Console\StreamPositionOfProjectionCommand;

final class ProjectorServiceProviderTest extends TestCaseWithOrchestra
{
    protected function getPackageProviders($app): array
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

        $this->assertEquals([
            'provider'   => [
                'eloquent'  => Projection::class,
                'in_memory' => InMemoryProjectionProvider::class,
            ],
            'projectors' => [
                'default'   => [
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
                'default'   => [],
                'lazy' => [
                    Option::OPTION_UPDATE_LOCK_THRESHOLD => 5000,
                ],
                'in_memory' => InMemoryProjectorOption::class,
                'snapshot'  => [],
            ],
            'console'    => [
                'load_migrations' => true,
                'load_commands'   => true,
                'commands'        => [
                    // write projection
                    StopProjectionCommand::class,
                    ResetProjectionCommand::class,
                    DeleteProjectionCommand::class,
                    DeleteIncProjectionCommand::class,

                    // read projection
                    StatusOfProjectionCommand::class,
                    StreamPositionOfProjectionCommand::class,
                    StateOfProjectionCommand::class,

                    // projection to optimize queries
                    ProjectAllStreamCommand::class,
                    ProjectCategoryStreamCommand::class,
                    ProjectMessageNameCommand::class,
                ],
            ],
        ], $config);
    }
}
