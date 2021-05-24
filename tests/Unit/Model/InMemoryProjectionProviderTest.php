<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Model;

use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Foundation\Clock\UniversalSystemClock;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Exception\InvalidArgumentException;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;

final class InMemoryProjectionProviderTest extends TestCaseWithProphecy
{
    private Clock|ObjectProphecy $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new UniversalSystemClock();
    }

    /**
     * @test
     */
    public function it_create_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertNull($provider->findByName('account'));

        $this->assertTrue($provider->createProjection('account', 'running'));

        $projection = $provider->findByName('account');

        $this->assertInstanceOf(ProjectionModel::class, $projection);
    }

    /**
     * @test
     */
    public function it_return_false_when_creating_projection_if_already_exists(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertNull($provider->findByName('account'));

        $this->assertTrue($provider->createProjection('account', 'running'));

        $projection = $provider->findByName('account');

        $this->assertInstanceOf(ProjectionModel::class, $projection);

        $this->assertFalse($provider->createProjection('account', 'running'));
    }

    /**
     * @test
     */
    public function it_update_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('account', 'running'));

        $projection = $provider->findByName('account');

        $this->assertEquals('account', $projection->name());
        $this->assertEquals('running', $projection->status());
        $this->assertEquals('{}', $projection->state());
        $this->assertEquals('{}', $projection->position());
        $this->assertNull($projection->lockedUntil());

        $this->assertFalse($provider->updateProjection('customer', []));

        $updated = $provider->updateProjection('account', [
            'state'        => '{"count":0}',
            'position'     => '{"customer":0}',
            'status'       => 'idle',
            'locked_until' => 'datetime',
        ]);

        $this->assertTrue($updated);

        $projection = $provider->findByName('account');

        $this->assertEquals('account', $projection->name());
        $this->assertEquals('idle', $projection->status());
        $this->assertEquals('{"count":0}', $projection->state());
        $this->assertEquals('{"customer":0}', $projection->position());
        $this->assertEquals('datetime', $projection->lockedUntil());
    }

    /**
     * @test
     */
    public function it_raise_exception_when_updating_projection_with_unknown_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid projection field invalid_field');

        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('account', 'running'));

        $provider->updateProjection('account', ['invalid_field' => '{"count" => 10}']);
    }

    /**
     * @test
     */
    public function it_delete_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('customer', 'running'));

        $this->assertInstanceOf(ProjectionModel::class, $provider->findByName('customer'));

        $deleted = $provider->deleteProjectionByName('customer');

        $this->assertTrue($deleted);

        $this->assertNull($provider->findByName('customer'));
    }

    /**
     * @test
     */
    public function it_return_false_deleting_not_found_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertNull($provider->findByName('customer'));

        $this->assertFalse($provider->deleteProjectionByName('customer'));
    }

    /**
     * @test
     */
    public function it_find_projection_by_names(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('customer', 'running'));
        $this->assertTrue($provider->createProjection('account', 'running'));
        $this->assertCount(1, $provider->findByNames('customer'));
        $this->assertCount(1, $provider->findByNames('account'));
        $this->assertCount(2, $provider->findByNames('customer', 'account'));

        $found = $provider->findByNames('customer', 'account', 'emails');

        $this->assertCount(2, $found);
        $this->assertEquals(['customer', 'account'], $found);
    }

    /**
     * @test
     */
    public function it_acquire_lock_with_null_lock_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('customer', 'idle'));
        $this->assertNull($provider->findByName('customer')->lockedUntil());

        $now = $this->clock->fromNow();
        $lock = $now->add('PT1H');

        $acquired = $provider->acquireLock('customer', 'running', $lock->toString(), $now->toString());
        $this->assertTrue($acquired);

        $projection = $provider->findByName('customer');

        $this->assertEquals($lock->toString(), $projection->lockedUntil());
        $this->assertEquals('running', $projection->status());
    }

    /**
     * @test
     */
    public function it_return_false_acquiring_lock_with_not_found_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertNull($provider->findByName('customer'));

        $acquired = $provider->acquireLock('customer', 'running', 'lock', 'now');

        $this->assertFalse($acquired);
    }

    /**
     * @test
     */
    public function it_acquire_lock_when_now_is_greater_than_lock_projection(): void
    {
        $now = $this->clock->fromNow();
        $lock = $now->sub('PT1H');

        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('customer', 'idle'));
        $this->assertNull($provider->findByName('customer')->lockedUntil());

        $provider->acquireLock('customer', 'running', $lock->toString(), $now->toString());
        $this->assertEquals($lock->toString(), $provider->findByName('customer')->lockedUntil());

        $provider->acquireLock('customer', 'running', $now->toString(), $now->toString());
        $this->assertEquals($now->toString(), $provider->findByName('customer')->lockedUntil());
    }

    /**
     * @test
     */
    public function it_does_not_acquire_lock_when_now_is_less_than_lock_projection(): void
    {
        $provider = new InMemoryProjectionProvider($this->clock);

        $this->assertTrue($provider->createProjection('customer', 'idle'));
        $this->assertNull($provider->findByName('customer')->lockedUntil());

        $now = $this->clock->fromNow();
        $lock = $now->add('PT1H');
        $acquired = $provider->acquireLock('customer', 'running', $lock->toString(), $now->toString());

        $this->assertTrue($acquired);
        $this->assertEquals($lock->toString(), $provider->findByName('customer')->lockedUntil());

        $notAcquired = $provider->acquireLock('customer', 'running', $lock->toString(), $now->toString());
        $this->assertFalse($notAcquired);

        $this->assertEquals($lock->toString(), $provider->findByName('customer')->lockedUntil());
    }
}
