<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Support\ReadModel;

use Generator;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Projector\Support\ReadModel\InteractWithConnection;
use function abs;

final class InteractWithConnectionTest extends TestCaseWithProphecy
{
    private ConnectionInterface|ObjectProphecy $connection;

    protected function setUp(): void
    {
        $this->connection = $this->prophesize(ConnectionInterface::class);
    }

    /**
     * @test
     */
    public function it_insert_data(): void
    {
        $queryBuilder = $this->prophesize(Builder::class);
        $this->connection->table('read_customer')->willReturn($queryBuilder);

        $queryBuilder->insert(['name' => 'steph'])->shouldBeCalled();

        $instance = $this->interactWithConnection();

        $instance('insert', ['name' => 'steph']);
    }

    /**
     * @test
     */
    public function it_update_data(): void
    {
        $queryBuilder = $this->prophesize(Builder::class);
        $this->connection->table('read_customer')->willReturn($queryBuilder)->shouldBeCalled();
        $queryBuilder->where('id', '123-')->willReturn($queryBuilder)->shouldBeCalled();

        $queryBuilder->update(['name' => 'steph'])->shouldBeCalled();

        $instance = $this->interactWithConnection();

        $this->assertEquals('id', $instance('getKey'));

        $instance('update', '123-', ['name' => 'steph']);
    }

    /**
     * @test
     * @dataProvider provideAmount
     */
    public function it_increment_absolute_value_with_data(int|float $amount): void
    {
        $queryBuilder = $this->prophesize(Builder::class);
        $this->connection->table('read_customer')->willReturn($queryBuilder);

        $queryBuilder->where('id', '123-')->willReturn($queryBuilder)->shouldBeCalled();
        $queryBuilder->increment('column_name', abs($amount), ['extra'])->shouldBeCalled();

        $instance = $this->interactWithConnection();

        $this->assertEquals('id', $instance('getKey'));

        $instance('increment', '123-', 'column_name', $amount, ['extra']);
    }

    /**
     * @test
     * @dataProvider provideAmount
     */
    public function it_decrement_absolute_value_with_data(int|float $amount): void
    {
        $queryBuilder = $this->prophesize(Builder::class);
        $this->connection->table('read_customer')->willReturn($queryBuilder);

        $queryBuilder->where('id', '123-')->willReturn($queryBuilder)->shouldBeCalled();
        $queryBuilder->decrement('column_name', abs($amount), ['extra'])->shouldBeCalled();

        $instance = $this->interactWithConnection();

        $this->assertEquals('id', $instance('getKey'));

        $instance('decrement', '123-', 'column_name', $amount, ['extra']);
    }

    private function interactWithConnection(): callable
    {
        $connection = $this->connection->reveal();

        return new class($connection) {
            use InteractWithConnection;

            public function __construct(protected ConnectionInterface $connection)
            {
            }

            public function __invoke(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }

            public function tableName(): string
            {
                return 'read_customer';
            }
        };
    }

    public function provideAmount(): Generator
    {
        yield [10];
        yield [-10];
        yield [5.5];
        yield [-5.5];
    }
}
