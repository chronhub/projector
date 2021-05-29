<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Factory;

use Closure;
use Exception;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Projector\Factory\DetectGap;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Foundation\Clock\UniversalSystemClock;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;

final class DetectGapTest extends TestCaseWithProphecy
{
    private StreamPosition|ObjectProphecy $streamPosition;
    private Clock|ObjectProphecy $clock;

    protected function setUp(): void
    {
        $this->streamPosition = $this->prophesize(StreamPosition::class);
        $this->clock = $this->prophesize(Clock::class);
    }

    /**
     * @test
     */
    public function it_can_be_constructed(): void
    {
        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $this->clock->reveal(),
            [0, 5, 10],
            'PT60S'
        );

        $this->assertFalse($gapDetector->hasGap());
        $this->assertRetries($gapDetector, 0);
    }

    /**
     * @test
     */
    public function it_does_not_detect_gap_when_retries_in_milliseconds_is_an_empty_array(): void
    {
        $time = UniversalPointInTime::now()->toString();

        $this->clock->fromNow()->shouldNotBeCalled();

        $this->streamPosition->hasNextPosition(Argument::type('string'), Argument::type('integer'))->shouldNotBeCalled();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $this->clock->reveal(),
            [],
            'PT60S'
        );

        $this->assertFalse($gapDetector->detect('customer', 10, $time));
        $this->assertFalse($gapDetector->hasGap());
    }

    /**
     * @test
     */
    public function it_does_not_detect_gap_when_next_position_is_not_available(): void
    {
        $clock = new UniversalSystemClock();
        $time = UniversalPointInTime::now();
        $eventTime = $time->sub('PT1S')->toString();

        $this->streamPosition->hasNextPosition('customer', 3)->willReturn(true)->shouldBeCalled();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $clock,
            [5, 10, 20],
            'PT60S'
        );

        $this->assertFalse($gapDetector->detect('customer', 3, $eventTime));
        $this->assertFalse($gapDetector->hasGap());
    }

    /**
     * @test
     */
    public function it_does_not_detect_gap_when_no_more_retries(): void
    {
        $clock = new UniversalSystemClock();
        $time = UniversalPointInTime::now();
        $eventTime = $time->sub('PT1S')->toString();

        $this->streamPosition->hasNextPosition('customer', 2)->willReturn(false)->shouldBeCalled();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $clock,
            [5, 10, 20],
            'PT60S'
        );

        $this->assertRetries($gapDetector, 0);
        $this->assertTrue($gapDetector->detect('customer', 2, $eventTime));

        $gapDetector->sleep();
        $this->assertRetries($gapDetector, 1);
        $this->assertTrue($gapDetector->detect('customer', 2, $eventTime));

        $gapDetector->sleep();
        $this->assertRetries($gapDetector, 2);
        $this->assertTrue($gapDetector->detect('customer', 2, $eventTime));

        $gapDetector->sleep();
        $this->assertRetries($gapDetector, 3);

        $this->assertFalse($gapDetector->detect('customer', 2, $eventTime));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_retries_in_retries_in_ms_does_not_exists(): void
    {
        $clock = new UniversalSystemClock();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $clock,
            [5, 10, 20],
            'PT60S'
        );

        $gapDetector->sleep();
        $gapDetector->sleep();
        $gapDetector->sleep();

        try {
            $gapDetector->sleep();
        } catch (Exception $exception) {
            $this->assertEquals('Undefined array key 3', $exception->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_detect_gap(): void
    {
        $clock = new UniversalSystemClock();
        $time = UniversalPointInTime::now();
        $eventTime = $time->sub('PT1S')->toString();

        $this->streamPosition->hasNextPosition('customer', 3)->willReturn(false)->shouldBeCalled();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $clock,
            [5, 10, 20],
            'PT60S'
        );

        $this->assertTrue($gapDetector->detect('customer', 3, $eventTime));
        $this->assertTrue($gapDetector->hasGap());
    }

    /**
     * @test
     */
    public function it_reset_retries(): void
    {
        $clock = new UniversalSystemClock();

        $gapDetector = new DetectGap(
            $this->streamPosition->reveal(),
            $clock,
            [5, 10, 20],
            'PT60S'
        );

        $gapDetector->sleep();
        $gapDetector->sleep();
        $gapDetector->sleep();

        $this->assertRetries($gapDetector, 3);

        $gapDetector->resetRetries();

        $this->assertRetries($gapDetector, 0);
    }

    private function assertRetries(DetectGap $instance, int $expectedRetries): void
    {
        $closure = Closure::bind(fn ($instance) => $instance->retries, null, DetectGap::class);

        $this->assertEquals($expectedRetries, $closure($instance));
    }
}
