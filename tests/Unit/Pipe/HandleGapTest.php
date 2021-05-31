<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Chronhub\Projector\Pipe\HandleGap;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\DetectGap;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\Contracts\Repository;

final class HandleGapTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_persist_projection_when_gap_is_detected(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->persist()->shouldBeCalled();

        $context = $this->prophesize(Context::class);

        $gap = $this->prophesize(DetectGap::class);
        $gap->hasGap()->willReturn(true)->shouldBeCalled();
        $gap->sleep()->shouldBeCalled();
        $gap->resetGap()->shouldBeCalled();

        $context->gap()->willReturn($gap->reveal())->shouldBeCalled();

        $pipe = new HandleGap($repository->reveal());

        $run =  $pipe($context->reveal(), fn (Context $context): bool => true);
        $this->assertTrue($run);
    }

    /**
     * @test
     */
    public function it_reset_retries_gap_when_gap_is_not_detected(): void
    {
        $repository = $this->prophesize(Repository::class);
        $repository->persist()->shouldNotBeCalled();

        $context = $this->prophesize(Context::class);

        $gap = $this->prophesize(DetectGap::class);
        $gap->hasGap()->willReturn(false)->shouldBeCalled();
        $gap->sleep()->shouldNotBeCalled();
        $gap->resetGap()->shouldNotBeCalled();
        $gap->resetRetries()->shouldBeCalled();

        $context->gap()->willReturn($gap->reveal())->shouldBeCalled();

        $pipe = new HandleGap($repository->reveal());

        $run =  $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }
}
