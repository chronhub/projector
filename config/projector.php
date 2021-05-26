<?php

declare(strict_types=1);

use Chronhub\Projector\Model\Projection;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Support\Contracts\Factory\Option;
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
use Chronhub\Projector\Support\Console\ProjectCategoryStreamCommand;
use Chronhub\Projector\Support\Console\StreamPositionOfProjectionCommand;

return [
    /*
    |--------------------------------------------------------------------------
    | Projection provider
    |--------------------------------------------------------------------------
    |
    */

    'provider' => [
        'eloquent' => Projection::class,

        'in_memory' => InMemoryProjectionProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Projectors
    |--------------------------------------------------------------------------
    |
    | Each projector is tied to an event store
    | caution as Dev is responsible to match connection between various services
    |
    |       chronicler:                 chronicler configuration key
    |       options:                    options key
    |       provider:                   projection provider key
    |       event_stream_provider:      from chronicler configuration key
    |       dispatch_projector_events:  dispatch on event projection status (start, stop, reset, delete)
    |       scope:                      projection query filter
    */

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

    /*
    |--------------------------------------------------------------------------
    | Projector options
    |--------------------------------------------------------------------------
    |
    | Options can be an array or a service implementing projector option contract
    | with pre defined options which can not be mutated
    |
    */
    'options'    => [
        'default' => [],

        'lazy' => [
            Option::OPTION_UPDATE_LOCK_THRESHOLD => 5000,
        ],

        'in_memory' => InMemoryProjectorOption::class,

        'snapshot' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console and commands
    |--------------------------------------------------------------------------
    |
    */
    'console'    => [
        'load_migrations' => true,

        'load_commands' => true,

        'commands' => [
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
];
