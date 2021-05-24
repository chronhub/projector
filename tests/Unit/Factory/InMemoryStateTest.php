<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Factory;

use Chronhub\Projector\Tests\TestCase;
use Chronhub\Projector\Factory\InMemoryState;

final class InMemoryStateTest extends TestCase
{
    public function it_can_be_constructed_with_empty_state(): void
    {
        $state = new InMemoryState();

        $this->assertEmpty($state->getState());
    }

    /**
     * @test
     */
    public function it_set_state(): void
    {
        $state = new InMemoryState();

        $state->setState(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $state->getState());
    }

    /**
     * @test
     */
    public function it_reset_state(): void
    {
        $state = new InMemoryState();

        $state->setState(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $state->getState());

        $state->resetState();

        $this->assertEquals([], $state->getState());
    }
}
