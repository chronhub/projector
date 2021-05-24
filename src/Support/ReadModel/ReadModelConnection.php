<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\ReadModel;

use Throwable;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Projector\Support\Contracts\Support\ReadModel;

abstract class ReadModelConnection implements ReadModel
{
    use InteractWithStack;
    use InteractWithConnection;

    public function __construct(protected ConnectionInterface $connection)
    {
    }

    public function initialize(): void
    {
        $this->connection->getSchemaBuilder()->create($this->tableName(), $this->up());
    }

    public function isInitialized(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($this->tableName());
    }

    public function reset(): void
    {
        $resetReadModel = function (): void {
            $schema = $this->connection->getSchemaBuilder();

            $schema->disableForeignKeyConstraints();

            $this->connection->table($this->tableName())->truncate();

            $schema->enableForeignKeyConstraints();
        };

        $this->transactional($resetReadModel);
    }

    public function down(): void
    {
        $dropReadModel = function (): void {
            $schema = $this->connection->getSchemaBuilder();

            $schema->disableForeignKeyConstraints();

            $schema->drop($this->tableName());

            $schema->enableForeignKeyConstraints();
        };

        $this->transactional($dropReadModel);
    }

    /**
     * @throws Throwable
     */
    protected function transactional(callable $process): void
    {
        $this->connection->beginTransaction();

        try {
            $process($this);
        } catch (Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        $this->connection->commit();
    }

    abstract protected function up(): callable;
}
