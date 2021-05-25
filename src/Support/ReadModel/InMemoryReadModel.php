<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\ReadModel;

use Chronhub\Projector\Support\Contracts\Support\ReadModel;

final class InMemoryReadModel implements ReadModel
{
    use InteractWithStack;

    private ?array $data;

    public function initialize(): void
    {
        $this->data = [];
    }

    public function isInitialized(): bool
    {
        return true;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function down(): void
    {
        $this->data = null;
    }

    protected function insert(string $id, array $data): void
    {
        $this->data[$id] = $data;
    }

    protected function update(string $id, string $field, mixed $value): void
    {
        $this->data[$id][$field] = $value;
    }

    protected function delete(string $id): void
    {
        unset($this->data[$id]);
    }
}
