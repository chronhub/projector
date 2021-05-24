<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Support\Scope;

use Generator;
use Chronhub\Projector\Exception\RuntimeException;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Tests\Double\SomeDomainEvent;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use function array_filter;

final class InMemoryProjectionQueryScopeTest extends TestCaseWithProphecy
{
    /**
     * @test
     * @dataProvider provideDomainEvents
     */
    public function it_filter_domain_events_by_internal_position_header(array $messages,
                                                                   int $expectedCount,
                                                                   int $position): void
    {
        $scope = new InMemoryProjectionQueryScope();

        $filter = $scope->fromIncludedPosition();
        $filter->setCurrentPosition($position);

        $messages = array_filter($messages, $filter->filterQuery());

        $this->assertCount($expectedCount, $messages);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_position_is_not_set(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Position must be greater than 0, current is 0');

        $scope = new InMemoryProjectionQueryScope();
        $filter = $scope->fromIncludedPosition();

        $filter->filterQuery();
    }

    /**
     * @test
     * @dataProvider provideInvalidPosition
     */
    public function it_raise_exception_when_position_is_less_than_zero(int $invalidPosition): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Position must be greater than 0, current is $invalidPosition");

        $scope = new InMemoryProjectionQueryScope();
        $filter = $scope->fromIncludedPosition();
        $filter->setCurrentPosition($invalidPosition);

        $filter->filterQuery();
    }

    public function provideDomainEvents(): Generator
    {
        $messages = [
            SomeDomainEvent::fromContent([])->withHeader(Header::INTERNAL_POSITION, 3),
            SomeDomainEvent::fromContent([])->withHeader(Header::INTERNAL_POSITION, 2),
            SomeDomainEvent::fromContent([])->withHeader(Header::INTERNAL_POSITION, 4),
            SomeDomainEvent::fromContent([])->withHeader(Header::INTERNAL_POSITION, 1),
        ];

        yield [$messages, 4, 1];

        yield [$messages, 2, 3];

        yield [$messages, 1, 4];

        yield [$messages, 0, 5];
    }

    public function provideInvalidPosition(): Generator
    {
        yield [0];
        yield [-1];
    }
}
