<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Closure;
use Generator;
use Chronhub\Projector\Status;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\RunnerController;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Pipe\PreparePersistentRunner;
use Chronhub\Projector\Support\Contracts\Repository;

final class PreparePersistentRunnerTest extends TestCaseWithProphecy
{
    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_load_remote_status_when_initiate_process(bool $runInBackground): void
    {
        $status = Status::RUNNING();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->initiate()->shouldbeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());

        $pipe = new PreparePersistentRunner($repository->reveal());

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
        $this->assertIsInitiated($pipe, true);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_stop_projection_on_stop_remote_status_loaded(bool $runInBackground): void
    {
        $status = Status::STOPPING();

        $repository = $this->prophesize(Repository::class);
        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->loadState()->shouldBeCalled();
        $repository->stop()->shouldBeCalled();
        $repository->initiate()->shouldNotBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());

        $pipe = new PreparePersistentRunner($repository->reveal());

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
        $this->assertIsInitiated($pipe, true);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_initiate_projection_on_reset_remote_status_loaded(bool $runInBackground): void
    {
        $status = Status::RESETTING();

        $repository = $this->prophesize(Repository::class);

        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->reset()->shouldBeCalled();
        $repository->startAgain()->shouldNotBeCalled();
        $repository->initiate()->shouldBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());

        $pipe = new PreparePersistentRunner($repository->reveal());

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
        $this->assertIsInitiated($pipe, true);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_initiate_projection_on_delete_remote_status_loaded(bool $runInBackground): void
    {
        $status = Status::DELETING();

        $repository = $this->prophesize(Repository::class);

        $repository->loadStatus()->willReturn($status)->shouldBeCalled();

        $repository->delete(false)->shouldBeCalled();
        $repository->startAgain()->shouldNotBeCalled();
        $repository->initiate()->shouldNotBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());

        $pipe = new PreparePersistentRunner($repository->reveal());

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
        $this->assertIsInitiated($pipe, true);
    }

    /**
     * @test
     * @dataProvider provideBoolean
     */
    public function it_initiate_projection_on_delete_with_events_remote_status_loaded(bool $runInBackground): void
    {
        $status = Status::DELETING_EMITTED_EVENTS();

        $repository = $this->prophesize(Repository::class);

        $repository->loadStatus()->willReturn($status)->shouldBeCalled();
        $repository->delete(true)->shouldBeCalled();
        $repository->startAgain()->shouldNotBeCalled();
        $repository->initiate()->shouldNotBeCalled();

        $runner = $this->prophesize(RunnerController::class);
        $runner->inBackground()->willReturn($runInBackground)->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->runner()->willReturn($runner->reveal());

        $pipe = new PreparePersistentRunner($repository->reveal());

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);
        $this->assertIsInitiated($pipe, true);
    }

    private function assertIsInitiated(PreparePersistentRunner $instance, bool $expect): void
    {
        $closure = Closure::bind(fn ($instance) => $instance->isInitiated, null, PreparePersistentRunner::class);

        $this->assertEquals($expect, $closure($instance));
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
