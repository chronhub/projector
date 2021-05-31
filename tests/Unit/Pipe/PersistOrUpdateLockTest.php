<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\DetectGap;
use Chronhub\Projector\Factory\EventCounter;
use Chronhub\Projector\Pipe\PersistOrUpdateLock;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Support\Contracts\Factory\Option;

final class PersistOrUpdateLockTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_return_next_context_when_gap_is_detected(): void
    {
        $repository  = $this->prophesize(Repository::class);

        $gap = $this->prophesize(DetectGap::class);

        $context  = $this->prophesize(Context::class);
        $context->gap()->willReturn($gap->reveal())->shouldBeCalled();
        $gap->hasGap()->willReturn(true)->shouldBeCalled();

        $pipe = new PersistOrUpdateLock($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     */
    public function it_sleep_before_updating_lock_if_event_counter_is_reset(): void
    {
        $repository  = $this->prophesize(Repository::class);
        $repository->updateLock()->shouldBeCalled();

        $gap = $this->prophesize(DetectGap::class);
        $eventCounter = $this->prophesize(EventCounter::class);
        $context  = $this->prophesize(Context::class);
        $option = $this->prophesize(Option::class);

        $context->gap()->willReturn($gap->reveal())->shouldBeCalled();
        $gap->hasGap()->willReturn(false)->shouldBeCalled();

        $context->option()->willReturn($option);
        $option->sleep()->willReturn(1000)->shouldBeCalled();

        $context->eventCounter()->willReturn($eventCounter->reveal());
        $eventCounter->isReset()->willReturn(true)->shouldBeCalled();

        $pipe = new PersistOrUpdateLock($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     */
    public function it_persist_when_event_counter_is_not_reset(): void
    {
        $repository  = $this->prophesize(Repository::class);
        $repository->persist()->shouldBeCalled();

        $gap = $this->prophesize(DetectGap::class);
        $eventCounter = $this->prophesize(EventCounter::class);
        $context  = $this->prophesize(Context::class);

        $context->gap()->willReturn($gap->reveal())->shouldBeCalled();
        $gap->hasGap()->willReturn(false)->shouldBeCalled();

        $context->eventCounter()->willReturn($eventCounter->reveal());
        $eventCounter->isReset()->willReturn(false)->shouldBeCalled();

        $pipe = new PersistOrUpdateLock($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }
}
