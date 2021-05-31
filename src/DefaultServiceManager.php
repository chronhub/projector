<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use Illuminate\Support\Arr;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Support\Contracts\Factory\Option;
use Chronhub\Projector\Support\Contracts\ServiceManager;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\Contracts\Factory\ChroniclerManager;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;
use function is_array;
use function is_string;

final class DefaultServiceManager implements ServiceManager
{
    /**
     * @var array<string,callable>
     */
    private array $customProjectors = [];

    /**
     * @var array<string,Manager>
     */
    private array $projectors = [];
    private array $config;

    public function __construct(protected Application $app)
    {
        $this->config = $app->get(Repository::class)->get('projector', []);
    }

    public function create(string $name = 'default'): Manager
    {
        if ($projector = $this->projectors[$name] ?? null) {
            return $projector;
        }

        $config = $this->fromProjector("projectors.$name");

        if ( ! is_array($config)) {
            throw new InvalidArgumentException("Invalid configuration for projector manager $name");
        }

        return $this->projectors[$name] = $this->resolveProjectorManager($name, $config);
    }

    public function extends(string $name, callable $manager): void
    {
        $this->customProjectors[$name] = $manager;
    }

    private function resolveProjectorManager(string $name, array $config): Manager
    {
        if ($customProjector = $this->customProjectors[$name] ?? null) {
            return $customProjector($this->app, $this->config);
        }

        return $this->createDefaultProjectorManager($config);
    }

    private function createDefaultProjectorManager(array $config): Manager
    {
        $dispatcher = null;

        if (true === ($config['dispatch_projector_events'] ?? false)) {
            $dispatcher = $this->app->get(Dispatcher::class);
        }

        return new DefaultManager(
            $this->app->get(ChroniclerManager::class)->create($config['chronicler']),
            $this->determineEventStreamProvider($config),
            $this->determineProjectionProvider($config),
            $this->app->make($config['scope']),
            $this->app->get(Clock::class),
            $dispatcher,
            $this->determineProjectorOptions($config['options'])
        );
    }

    private function determineProjectorOptions(?string $optionKey): array|Option
    {
        $options = $this->fromProjector("options.$optionKey") ?? [];

        return is_array($options) ? $options : $this->app->make($options);
    }

    private function determineEventStreamProvider(array $config): EventStreamProvider
    {
        $eventStreamKey = $config['event_stream_provider'];

        $eventStream = $this->app[Repository::class]->get("chronicler.provider.$eventStreamKey");

        if ( ! is_string($eventStream)) {
            throw new InvalidArgumentException("Event stream provider with key $eventStreamKey must be a string");
        }

        return $this->app->make($eventStream);
    }

    private function determineProjectionProvider(array $config): ProjectionProvider
    {
        $projectionKey = $config['provider'];

        $projection = $this->fromProjector("provider.$projectionKey") ?? null;

        if ( ! is_string($projection)) {
            throw new InvalidArgumentException("Unable to determine projection provider with key $projectionKey");
        }

        return $this->app->make($projection);
    }

    private function fromProjector(string $key): mixed
    {
        return Arr::get($this->config, $key);
    }
}
