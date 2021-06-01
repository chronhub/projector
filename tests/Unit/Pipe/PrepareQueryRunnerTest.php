<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Pipe;

use Closure;
use Chronhub\Projector\Context\Context;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Projector\Pipe\PrepareQueryRunner;
use Chronhub\Projector\Tests\TestCaseWithProphecy;

final class PrepareQueryRunnerTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_initiate_by_loading_streams(): void
    {
        $streamPosition = $this->prophesize(StreamPosition::class);
        $streamPosition->watch(['customer', 'account'])->shouldBeCalled();

        $context = $this->prophesize(Context::class);
        $context->streamPosition()->willReturn($streamPosition)->shouldBeCalled();
        $context->queries()->willReturn(['customer', 'account'])->shouldBeCalled();

        $pipe = new PrepareQueryRunner();

        $this->assertIsInitiated($pipe, false);

        $run = $pipe($context->reveal(), fn (Context $context): bool => true);

        $this->assertTrue($run);

        $this->assertIsInitiated($pipe, true);
    }

    private function assertIsInitiated(PrepareQueryRunner $instance, bool $expect): void
    {
        $closure = Closure::bind(fn ($instance) => $instance->isInitiated, null, PrepareQueryRunner::class);

        $this->assertEquals($expect, $closure($instance));
    }
}
