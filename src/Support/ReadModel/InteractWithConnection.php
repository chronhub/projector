<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\ReadModel;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;
use function abs;

trait InteractWithConnection
{
    protected ConnectionInterface $connection;

    protected function insert(array $data): void
    {
        $this->queryBuilder()->insert($data);
    }

    protected function update(string $id, array $data): void
    {
        $this->queryBuilder()->where($this->getKey(), $id)->update($data);
    }

    protected function increment(string $id, string $column, int|float $value, array $extra = []): void
    {
        $this->queryBuilder()
            ->where($this->getKey(), $id)
            ->increment($column, abs($value), $extra);
    }

    protected function decrement(string $id, string $column, int|float $value, array $extra = []): void
    {
        $this->queryBuilder()
            ->where($this->getKey(), $id)
            ->decrement($column, abs($value), $extra);
    }

    protected function queryBuilder(): Builder
    {
        return $this->connection->table($this->tableName());
    }

    protected function getKey(): string
    {
        return 'id';
    }

    abstract protected function tableName(): string;
}
