<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Support\ReadModel;

use Closure;
use RuntimeException;
use Prophecy\Argument;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\Query\Builder;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Chronhub\Projector\Support\ReadModel\ReadModelConnection;

final class ReadModelConnectionTest extends TestCaseWithProphecy
{
    private Connection|ObjectProphecy $connection;

    protected function setUp(): void
    {
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     */
    public function it_initialize_read_model(): void
    {
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->create('read_customer', Argument::type(Closure::class));

        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();

        $instance->initialize();
    }

    /**
     * @test
     */
    public function it_check_if_read_model_is_initialized(): void
    {
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);

        $schemaBuilder->hasTable('read_customer')->willReturn(true)->shouldBeCalled();
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();
        $this->assertTrue($instance->isInitialized());
    }

    /**
     * @test
     */
    public function it_can_reset_read_model(): void
    {
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->disableForeignKeyConstraints()->shouldBeCalled();
        $schemaBuilder->enableForeignKeyConstraints()->shouldBeCalled();

        $queryBuilder = $this->prophesize(Builder::class);
        $queryBuilder->truncate()->shouldBeCalled();

        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();
        $this->connection->table('read_customer')->willReturn($queryBuilder);

        $this->connection->beginTransaction()->shouldBeCalled();
        $this->connection->commit()->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();

        $instance->reset();
    }

    /**
     * @test
     */
    public function it_rollback_transaction_on_truncate_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('foo');

        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->disableForeignKeyConstraints()->shouldBeCalled();
        $schemaBuilder->enableForeignKeyConstraints()->shouldNotBeCalled();

        $exception = new RuntimeException('foo');
        $queryBuilder = $this->prophesize(Builder::class);
        $queryBuilder->truncate()
            ->willThrow($exception)
            ->shouldBeCalled();

        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();
        $this->connection->table('read_customer')->willReturn($queryBuilder);

        $this->connection->beginTransaction()->shouldBeCalled();
        $this->connection->rollBack()->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();

        $instance->reset();
    }

    /**
     * @test
     */
    public function it_delete_read_model(): void
    {
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->disableForeignKeyConstraints()->shouldBeCalled();
        $schemaBuilder->enableForeignKeyConstraints()->shouldBeCalled();
        $schemaBuilder->drop('read_customer')->shouldBeCalled();

        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();

        $this->connection->beginTransaction()->shouldBeCalled();
        $this->connection->commit()->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();
        $instance->down();
    }

    /**
     * @test
     */
    public function it_rollback_transaction_on_delete_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('foo');

        $exception = new RuntimeException('foo');

        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->disableForeignKeyConstraints()->shouldBeCalled();
        $schemaBuilder->enableForeignKeyConstraints()->shouldNotBeCalled();
        $schemaBuilder
            ->drop('read_customer')
            ->willThrow($exception)
            ->shouldBeCalled();

        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalled();

        $this->connection->beginTransaction()->shouldBeCalled();
        $this->connection->rollBack()->shouldBeCalled();

        $instance = $this->readModelConnectionInstance();

        $instance->down();
    }

    private function readModelConnectionInstance(): ReadModelConnection
    {
        $connection = $this->connection->reveal();

        return new class($connection) extends ReadModelConnection {
            protected function up(): callable
            {
                return function (): void { };
            }

            protected function tableName(): string
            {
                return 'read_customer';
            }
        };
    }
}
