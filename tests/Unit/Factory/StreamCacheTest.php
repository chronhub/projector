<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Factory;

use Generator;
use Chronhub\Projector\Tests\TestCase;
use Chronhub\Projector\Factory\StreamCache;
use Chronhub\Projector\Exception\InvalidArgumentException;

final class StreamCacheTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_constructed_with_cache_size(): void
    {
        $cache = new StreamCache(5);

        $this->assertCount(5, $cache->all());
    }

    /**
     * @test
     * @dataProvider provideInvalidCacheSize
     */
    public function it_raise_exception_with_cache_size_less_or_equals_than_zero(int $cacheSize): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream cache size must be greater than 0');

        new StreamCache($cacheSize);
    }

    /**
     * @test
     */
    public function it_fill_cache_to_the_next_position(): void
    {
        $cache = new StreamCache(2);

        $streamName = 'foo';

        $cache->push($streamName);

        $this->assertEquals(['foo', null], $cache->all());
    }

    /**
     * @test
     */
    public function it_override_first_position_if_cache_size_is_full(): void
    {
        $cache = new StreamCache(2);

        $firstStream = 'foo';
        $cache->push($firstStream);

        $this->assertEquals('foo', $cache->all()[0]);
        $this->assertNull($cache->all()[1]);

        $secondStream = 'bar';
        $cache->push($secondStream);

        $this->assertEquals('foo', $cache->all()[0]);
        $this->assertEquals('bar', $cache->all()[1]);

        $thirdStream = 'foo_bar';
        $cache->push($thirdStream);

        $this->assertEquals('foo_bar', $cache->all()[0]);
        $this->assertEquals('bar', $cache->all()[1]);
    }

    /**
     * @test
     */
    public function it_check_if_cache_has_stream_in_any_positions(): void
    {
        $cache = new StreamCache(2);

        $firstStream = 'foo';

        $this->assertFalse($cache->has($firstStream));

        $cache->push($firstStream);

        $this->assertTrue($cache->has($firstStream));
    }

    public function provideInvalidCacheSize(): Generator
    {
        yield [0];
        yield [-1];
    }

    public function provideInvalidPosition(): Generator
    {
        yield [-1];
        yield [2];
    }
}
