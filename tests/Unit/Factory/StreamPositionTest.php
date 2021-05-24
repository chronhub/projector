<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Factory;

use Generator;
use Chronhub\Projector\Factory\StreamPosition;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Chronicler\Support\Contracts\Model\EventStreamProvider;

final class StreamPositionTest extends TestCaseWithProphecy
{
    /**
     * @test
     */
    public function it_can_be_constructed(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $this->assertEquals(['customer' => 0, 'account' => 0], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_discover_all_streams(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class);

        $provider->allStreamWithoutInternal()->willReturn(
            ['customer', 'account']
        )->shouldBeCalled();

        $streamPosition = new StreamPosition($provider->reveal());

        $streamPosition->watch(['all' => true]);

        $this->assertEquals(['customer' => 0, 'account' => 0], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_discover_categories_streams(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class);

        $provider->filterByCategories(['account', 'customer'])->willReturn(
            ['customer-123', 'account-123']
        )->shouldBeCalled();

        $streamPosition = new StreamPosition($provider->reveal());

        $streamPosition->watch(['categories' => ['account', 'customer']]);

        $this->assertEquals(['customer-123' => 0, 'account-123' => 0], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_discover_streams_names(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $this->assertEquals(['customer' => 0, 'account' => 0], $streamPosition->toArray());
    }

    /**
     * @test
     * @dataProvider provideInvalidStreamsNames
     */
    public function it_raise_exception_when_stream_names_is_empty(array $streamNames): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->expectExceptionMessage('Stream names can not be empty');

        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch($streamNames);
    }

    /**
     * @test
     */
    public function it_merge_remote_streams(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $streamPosition->discover(['account' => 25, 'customer' => 25]);

        $this->assertEquals(['customer' => 25, 'account' => 25], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_merge_remote_streams_with_a_new_stream(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $streamPosition->discover(['account' => 25, 'customer' => 25, 'passwords' => 10]);

        $this->assertEquals(['customer' => 25, 'account' => 25, 'passwords' => 10], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_set_stream_at_position(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $streamPosition->discover(['account' => 25, 'customer' => 25]);

        $this->assertEquals(['customer' => 25, 'account' => 25], $streamPosition->toArray());

        $streamPosition->bind('account', 26);

        $this->assertEquals(['customer' => 25, 'account' => 26], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_reset_stream_positions(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $this->assertEquals(['customer' => 0, 'account' => 0], $streamPosition->toArray());

        $streamPosition->reset();

        $this->assertEquals([], $streamPosition->toArray());
    }

    /**
     * @test
     */
    public function it_check_if_stream_exists_at_position(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $this->assertTrue($streamPosition->has('account', 0));
        $this->assertFalse($streamPosition->has('account', 10));
        $this->assertTrue($streamPosition->has('customer', 0));
        $this->assertFalse($streamPosition->has('customer', 5));
    }

    /**
     * @test
     */
    public function it_convert_to_json(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $streamPosition = new StreamPosition($provider);

        $streamPosition->watch(['names' => ['account', 'customer']]);

        $this->assertEquals('{"account":0,"customer":0}', $streamPosition->toJson());
    }

    public function provideInvalidStreamsNames(): Generator
    {
        yield [[]];
        yield [['names' => []]];
        yield [['names' => null]];
        yield [['invalid_names' => []]];
    }
}
