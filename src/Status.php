<?php

declare(strict_types=1);

namespace Chronhub\Projector;

use MabeEnum\Enum;

/**
 * @method static Status RUNNING()
 * @method static Status STOPPING()
 * @method static Status DELETING()
 * @method static Status DELETING_EMITTED_EVENTS()
 * @method static Status RESETTING()
 * @method static Status IDLE()
 */
final class Status extends Enum
{
    public const RUNNING = 'running';
    public const STOPPING = 'stopping';
    public const DELETING = 'deleting';
    public const DELETING_EMITTED_EVENTS = 'deleting_emitted_events';
    public const RESETTING = 'resetting';
    public const IDLE = 'idle';
}
