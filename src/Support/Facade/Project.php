<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Facade;

use Illuminate\Support\Facades\Facade;
use Chronhub\Projector\Support\Contracts\Manager;

/**
 * @method static Manager create(string $driver = 'default')
 */
final class Project extends Facade
{
    const SERVICE_NAME = 'projector.manager';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_NAME;
    }
}
