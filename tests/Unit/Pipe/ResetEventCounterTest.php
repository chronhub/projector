<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\EventCounter;
use Chronhub\Projector\Pipe\ResetEventCounter;
use Chronhub\Projector\Tests\TestCaseWithProphecy;

final class ResetEventCounterTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_reset_event_counter(): void
    {
        $eventCounter = $this->prophesize(EventCounter::class);
        $eventCounter->reset()->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->eventCounter()->willReturn($eventCounter)->shouldBeCalled();

        $pipe = new ResetEventCounter();

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }
}
