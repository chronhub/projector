<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Model;

use Chronhub\Projector\Tests\TestCase;
use Chronhub\Projector\Model\InMemoryProjection;

final class InMemoryProjectionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_instantiated(): void
    {
        $projection = InMemoryProjection::create('customer', 'running');

        $this->assertEquals('customer', $projection->name());
        $this->assertEquals('running', $projection->status());
        $this->assertEquals('{}', $projection->state());
        $this->assertEquals('{}', $projection->position());
        $this->assertNull($projection->lockedUntil());
    }

    /**
     * @test
     */
    public function it_set_state(): void
    {
        $projection = InMemoryProjection::create('customer', 'running');

        $projection->setState('{}');
        $this->assertEquals('{}', $projection->state());

        $projection->setState('{"count": 10}');
        $this->assertEquals('{"count": 10}', $projection->state());
    }

    /**
     * @test
     */
    public function it_set_position(): void
    {
        $projection = InMemoryProjection::create('customer', 'running');

        $projection->setPosition('{}');
        $this->assertEquals('{}', $projection->position());

        $projection->setPosition('{"account": 10}');
        $this->assertEquals('{"account": 10}', $projection->position());
    }

    /**
     * @test
     */
    public function it_set_locked_until(): void
    {
        $projection = InMemoryProjection::create('customer', 'running');

        $this->assertNull($projection->lockedUntil());

        $projection->setLockedUntil('lock');
        $this->assertEquals('lock', $projection->lockedUntil());

        $projection->setLockedUntil(null);
        $this->assertEquals(null, $projection->lockedUntil());
    }
}
