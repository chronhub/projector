<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Generator;
use Chronhub\Projector\Status;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\RunnerController;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\Contracts\Repository;
use Chronhub\Projector\Pipe\UpdateStatusAndPositions;

final class UpdateStatusAndPositionsTest extends TestCaseWithProphecy
{
    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_reload_remote_status(bool $runInBackground): void
    {
        $status = Status::IDLE();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());
        $context->streamPosition()->willReturn($streamPosition->reveal());
        $context->queries()->willReturn(['customer'])->shouldBeCalled();

        $pipe = new UpdateStatusAndPositions($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_stop_on_stop_status_loaded(bool $runInBackground): void
    {
        $status = Status::STOPPING();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->loadState()->shouldNotBeCalled();
        $repository->stop()->shouldBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());
        $context->streamPosition()->willReturn($streamPosition->reveal());
        $context->queries()->willReturn(['customer'])->shouldBeCalled();

        $pipe = new UpdateStatusAndPositions($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_reset_on_resetting_status_loaded(bool $runInBackground): void
    {
        $status = Status::RESETTING();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->reset()->shouldBeCalled();

        $runInBackground
            ? $repository->startAgain()->shouldBeCalled()
            : $repository->startAgain()->shouldNotBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());
        $context->streamPosition()->willReturn($streamPosition->reveal());
        $context->queries()->willReturn(['customer'])->shouldBeCalled();

        $pipe = new UpdateStatusAndPositions($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_delete_on_deleting_status_loaded(bool $runInBackground): void
    {
        $status = Status::DELETING();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->delete(false)->shouldBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());
        $context->streamPosition()->willReturn($streamPosition->reveal());
        $context->queries()->willReturn(['customer'])->shouldBeCalled();

        $pipe = new UpdateStatusAndPositions($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_delete_with_events_on_deleting_with_events_status_loaded(bool $runInBackground): void
    {
        $status = Status::DELETING_EMITTED_EVENTS();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->delete(true)->shouldBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());
        $context->streamPosition()->willReturn($streamPosition->reveal());
        $context->queries()->willReturn(['customer'])->shouldBeCalled();

        $pipe = new UpdateStatusAndPositions($repository->reveal());

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
