<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Generator;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\RunnerController;
use Chronhub\Projector\Pipe\StopWhenRunningOnce;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\Contracts\PersistentProjector;

final class StopWhenRunningOnceTest extends TestCaseWithProphecy
{
    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_stop_projection(bool $runInBackground, bool $isStopped, bool $expectResult): void
    {
        $projector = $this->prophesize(PersistentProjector::class);

        $expectResult
            ? $projector->stop()->shouldbeCalled()
            : $projector->stop()->shouldNotBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldbeCalled();

        if ( ! $runInBackground) {
            $runner->isStopped()->willReturn($isStopped)->shouldbeCalled();
        }

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner);

        $pipe = new StopWhenRunningOnce($projector->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    public function provideBoolean(): Generator
    {
        yield [true, true, false];
        yield [true, false, false];
        yield [false, false, true];
    }
}
