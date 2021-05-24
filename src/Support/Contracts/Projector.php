<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

interface Projector
{
    public function run(bool $inBackground): void;

    public function stop(): void;

    public function reset(): void;

    public function getState(): array;
}
