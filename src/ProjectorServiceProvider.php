<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Illuminate\Support\ServiceProvider;
use Chronhub\Projector\Support\Facade\Project;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Projector\Support\Contracts\ServiceManager;

final class ProjectorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @var Application
     */
    public $app;

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$this->getConfigPath() => config_path('projector.php')],
                'config'
            );

            $console = config('projector.console') ?? [];

            if (true === $console['load_migrations'] ?? false) {
                $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            }

            if (true === $console['load_commands'] ?? false) {
                $this->commands($console['commands'] ?? []);
            }
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'projector');

        $this->app->singleton(ServiceManager::class, DefaultServiceManager::class);
        $this->app->alias(ServiceManager::class, Project::SERVICE_NAME);
    }

    public function provides(): array
    {
        return [ServiceManager::class, Project::SERVICE_NAME];
    }

    private function getConfigPath(): string
    {
        return __DIR__ . '/../config/projector.php';
    }
}
