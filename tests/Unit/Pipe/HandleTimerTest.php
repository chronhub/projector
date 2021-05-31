<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Pipe\HandleTimer;
use Chronhub\Projector\RunnerController;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\Contracts\Projector;
use Chronhub\Projector\Support\Contracts\Factory\Timer;

final class HandleTimerTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_stop_projection_when_timer_expired(): void
    {
        $projector = $this->prophesize(Projector::class);
        $projector->stop()->shouldBeCalled();

        $context = $this->prophesize(Context::class);

        $timer = $this->prophesize(Timer::class);
        $timer->start()->shouldBeCalled();
        $timer->isExpired()->willReturn(true)->shouldBeCalled();

        $context->timer()->willReturn($timer)->shouldBeCalled();

        $runner = new RunnerController();

        $context->runner()->willReturn($runner);

        $pipe = new HandleTimer($projector->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     */
    public function it_does_not_already_stop_projection_even_when_timer_expired(): void
    {
        $projector = $this->prophesize(Projector::class);
        $projector->stop()->shouldNotBeCalled();

        $context = $this->prophesize(Context::class);

        $timer = $this->prophesize(Timer::class);
        $timer->start()->shouldBeCalled();
        $timer->isExpired()->shouldNotBeCalled();

        $context->timer()->willReturn($timer)->shouldBeCalled();

        $runner = new RunnerController();
        $runner->stop(true);

        $context->runner()->willReturn($runner);

        $pipe = new HandleTimer($projector->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }
}
