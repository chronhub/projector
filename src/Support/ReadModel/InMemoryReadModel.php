<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\ReadModel;

use Chronhub\Projector\Support\Contracts\Support\ReadModel;
use function abs;

final class InMemoryReadModel implements ReadModel
{
    use InteractWithStack;

    private ?array $container;

    public function initialize(): void
    {
        $this->container = [];
    }

    public function isInitialized(): bool
    {
        return true;
    }

    public function reset(): void
    {
        $this->container = [];
    }

    public function down(): void
    {
        $this->container = null;
    }

    public function getContainer(): array
    {
        return $this->container;
    }

    protected function insert(string $id, array $data): void
    {
        $this->container[$id] = $data;
    }

    protected function update(string $id, string $field, mixed $value): void
    {
        $this->container[$id][$field] = $value;
    }

    protected function increment(string $id, string $field, int|float $value, array $extra = []): void
    {
        $this->container[$id][$field] += abs($value);

        foreach ($extra as $extraField => $extraValue) {
            $this->update($id, $extraField, $extraValue);
        }
    }

    protected function decrement(string $id, string $field, int|float $value, array $extra = []): void
    {
        $this->container[$id][$field] -= abs($value);

        foreach ($extra as $extraField => $extraValue) {
            $this->update($id, $extraField, $extraValue);
        }
    }

    protected function delete(string $id): void
    {
        unset($this->container[$id]);
    }
}
