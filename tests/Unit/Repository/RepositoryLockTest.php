<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Repository;

use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Projector\Repository\RepositoryLock;
use Chronhub\Projector\Tests\TestCaseWithProphecy;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Foundation\Clock\UniversalSystemClock;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Foundation\Support\Contracts\Clock\PointInTime;
use function sleep;
use function usleep;

/** @coversDefaultClass \Chronhub\Projector\Repository\RepositoryLock */
final class RepositoryLockTest extends TestCaseWithProphecy
{
    private Clock|ObjectProphecy $clock;
    private PointInTime|UniversalSystemClock $time;

    protected function setUp(): void
    {
        $this->clock = $this->prophesize(Clock::class);
        $this->time = UniversalPointInTime::now();
    }

    /**
     * @test
     */
    public function it_can_be_constructed(): void
    {
        $lock = new RepositoryLock($this->clock->reveal(), 1000, 1000);

        $this->assertNull($lock->lastLockUpdate());
    }

    /**
     * @test
     */
    public function it_acquire_lock(): void
    {
        $this->clock->fromNow()->willReturn($this->time)->shouldBeCalled();

        $lock = new RepositoryLock($this->clock->reveal(), 1000, 1000);

        $this->assertNull($lock->lastLockUpdate());

        $lockUntil = $lock->acquire();
        $updatedTime = $this->time->add('PT1S'); // lockTimeoutMs

        $this->assertEquals($updatedTime->toString(), $lock->currentLock());
        $this->assertEquals($updatedTime->toString(), $lockUntil);
    }

    /**
     * @test
     */
    public function it_always_update_lock_when_last_lock_update_is_not_set(): void
    {
        $this->clock->fromNow()->willReturn($this->time)->shouldBeCalled();
        $this->clock->fromDateTime($this->time->dateTime())->willReturn($this->time)->shouldBeCalled();

        $lock = new RepositoryLock($this->clock->reveal(), 1000, 1000);

        $this->assertNull($lock->lastLockUpdate());

        usleep(5);

        $this->assertTrue($lock->update());

        $this->assertEquals($this->time, $lock->lastLockUpdate());
    }

    /**
     * @test
     */
    public function it_always_update_lock_when_lock_threshold_is_zero(): void
    {
        $this->clock->fromNow()->willReturn($this->time)->shouldBeCalled();
        $this->clock->fromDateTime($this->time->dateTime())->willReturn($this->time)->shouldBeCalled();

        $lock = new RepositoryLock($this->clock->reveal(), 1000, 0);

        $this->assertNull($lock->lastLockUpdate());

        $this->assertTrue($lock->update());

        $this->assertEquals($this->time, $lock->lastLockUpdate());
    }

    /**
     * @test
     */
    public function it_update_lock_when_incremented_last_lock_is_less_than_last_lock_updated(): void
    {
        $lock = new RepositoryLock(new UniversalSystemClock(), 1000, 1000);

        $lock->acquire();

        sleep(1);

        $this->assertTrue($lock->update());
    }

    /**
     * @test
     */
    public function it_update_lock_when_incremented_last_lock_is_greater_than_last_lock_updated(): void
    {
        // TODO reset tests
        $lock = new RepositoryLock(new UniversalSystemClock(), 1000, 1000);

        $lock->acquire();

        $this->assertFalse($lock->update());
    }

    /**
     * @test
     */
    public function it_return_refresh_last_lock_update_with_lock_timeout_ms(): void
    {
        $time = $this->time;

        $this->clock->fromNow()->willReturn($time)->shouldBeCalled();

        $lock = new RepositoryLock($this->clock->reveal(), 1000, 1000);

        $this->assertEquals($time->add('PT1S')->toString(), $lock->refresh());
    }
}
