<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Factory;

use Generator;
use Chronhub\Projector\Tests\TestCase;
use Illuminate\Support\LazyCollection;
use Chronhub\Chronicler\Exception\StreamNotFound;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Factory\StreamEventIterator;
use Chronhub\Projector\Tests\Double\SomeDomainEvent;
use Chronhub\Foundation\Support\Contracts\Message\Header;

final class StreamEventIteratorTest extends TestCase
{
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->events = [
            SomeDomainEvent::fromContent(['foo' => 'bar'])->withHeader(
                Header::INTERNAL_POSITION, 1
            ),

            SomeDomainEvent::fromContent(['foo' => 'baz'])->withHeader(
                Header::INTERNAL_POSITION, 2
            ),
        ];
    }

    /**
     * @test
     */
    public function it_can_be_constructed_with_events_generator(): void
    {
        $iterator = new StreamEventIterator($this->provideEvents());

        $this->assertEquals($this->events[0], $iterator->current());
        $this->assertEquals($iterator->key(), $this->events[0]->header(Header::INTERNAL_POSITION));

        $iterator->next();

        $this->assertEquals($this->events[1], $iterator->current());
        $this->assertEquals($iterator->key(), $this->events[1]->header(Header::INTERNAL_POSITION));
    }

    /**
     * @test
     */
    public function it_raise_exception_with_invalid_internal_position_header(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream event position must be greater than 0');

        new StreamEventIterator($this->provideInvalidEvents());
    }

    /**
     * @test
     */
    public function it_raise_exception_with_no_internal_position_header(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream event position must be greater than 0');

        new StreamEventIterator($this->provideInvalidEvents2());
    }

    /**
     * @test
     */
    public function it_catch_stream_not_found_exception_with_empty_iterator(): void
    {
        $iterator = new StreamEventIterator($this->provideStreamNotFoundWhileIterating());

        $this->assertFalse($iterator->key());
        $this->assertNull($iterator->current());
        $this->assertFalse($iterator->valid());
    }

    public function provideEvents(): Generator
    {
        yield from $this->events;
    }

    public function provideInvalidEvents(): Generator
    {
        yield from [SomeDomainEvent::fromContent(['foo' => 'bar'])
                        ->withHeader(Header::INTERNAL_POSITION, 0), ];
    }

    public function provideInvalidEvents2(): Generator
    {
        yield from [SomeDomainEvent::fromContent(['foo' => 'bar'])];
    }

    public function provideStreamNotFoundWhileIterating(): Generator
    {
        yield from (new LazyCollection())->whenEmpty(function (): void {
            throw new StreamNotFound('stream not found');
        });
    }
}
