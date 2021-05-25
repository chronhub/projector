<?php

declare(strict_types=1);

namespace Chronhub\Projector\Support\Contracts;

interface ServiceManager
{
    public function create(string $name): Manager;

    public function extends(string $name, callable $manager): void;
}
