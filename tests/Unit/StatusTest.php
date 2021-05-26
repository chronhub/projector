<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit;

use Chronhub\Projector\Status;
use Chronhub\Projector\Tests\TestCase;

final class StatusTest extends TestCase
{
    /**
     * @test
     */
    public function it_fix_statuses_values(): void
    {
        $this->assertEquals([
            'running',
            'stopping',
            'deleting',
            'deleting_emitted_events',
            'resetting',
            'idle',
        ], Status::getValues());
    }

    /**
     * @test
     */
    public function it_fix_statuses_constants(): void
    {
        $this->assertEquals('running', Status::RUNNING);
        $this->assertEquals('stopping', Status::STOPPING);
        $this->assertEquals('deleting', Status::DELETING);
        $this->assertEquals('deleting_emitted_events', Status::DELETING_EMITTED_EVENTS);
        $this->assertEquals('resetting', Status::RESETTING);
        $this->assertEquals('idle', Status::IDLE);
    }
}
