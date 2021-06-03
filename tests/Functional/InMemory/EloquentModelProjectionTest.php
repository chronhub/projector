<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Chronhub\Projector\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Chronhub\Projector\Model\Projection;
use Chronhub\Projector\ProjectorServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;

final class EloquentModelProjectionTest extends TestCaseWithOrchestra
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_create_projection(): void
    {
        $projection = new Projection();

        $this->assertInstanceOf(ProjectionModel::class, $projection);
        $this->assertInstanceOf(ProjectionProvider::class, $projection);

        $created = $projection->createProjection('account', Status::IDLE);
        $this->assertTrue($created);

        /** @var ProjectionModel|Model $accountProjection */
        $accountProjection = $projection->newQuery()->find(1);

        $this->assertEquals([
            'no'           => 1,
            'name'         => 'account',
            'position'     => '{}',
            'state'        => '{}',
            'status'       => 'idle',
            'locked_until' => null,
        ], $accountProjection->toArray());

        $this->assertEquals('account', $accountProjection->name());
        $this->assertEquals('{}', $accountProjection->position());
        $this->assertEquals('{}', $accountProjection->state());
        $this->assertEquals('idle', $accountProjection->status());
        $this->assertNull($accountProjection->lockedUntil());
    }

    /**
     * @test
     */
    public function it_update_projection_by_projection_name(): void
    {
        $projection = new Projection();

        $created = $projection->createProjection('account', Status::IDLE);
        $this->assertTrue($created);

        /** @var ProjectionModel|Model $accountProjection */
        $accountProjection = $projection->newQuery()->find(1);

        $this->assertEquals([
            'no'           => 1,
            'name'         => 'account',
            'position'     => '{}',
            'state'        => '{}',
            'status'       => 'idle',
            'locked_until' => null,
        ], $accountProjection->toArray());

        $this->assertEquals('account', $accountProjection->name());
        $this->assertEquals('{}', $accountProjection->position());
        $this->assertEquals('{}', $accountProjection->state());
        $this->assertEquals('idle', $accountProjection->status());
        $this->assertNull($accountProjection->lockedUntil());

        $projection->updateProjection('account', [
            'position'     => '{"customer":10}',
            'state'        => '{"customers_count":0}',
            'status'       => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ]);

        $this->assertEquals([
            'no'           => 1,
            'name'         => 'account',
            'position'     => '{"customer":10}',
            'state'        => '{"customers_count":0}',
            'status'       => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ], $projection->newQuery()->find(1)->toArray());
    }

    /**
     * @test
     */
    public function it_raise_exception_if_projection_name_already_exists(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23000]: Integrity constraint violation');

        $projection = new Projection();

        $projection->createProjection('account', Status::IDLE);
        $projection->createProjection('account', Status::RUNNING);
    }

    /**
     * @test
     */
    public function it_assert_projection_exists(): void
    {
        $projection = new Projection();

        $this->assertFalse($projection->projectionExists('account'));

        $projection->createProjection('account', Status::IDLE);

        $this->assertTrue($projection->projectionExists('account'));
    }

    /**
     * @test
     */
    public function it_return_false_when_assert_projection_exists_and_query_exception_raised(): void
    {
        $this->markTestIncomplete(__METHOD__);
    }

    /**
     * @test
     */
    public function it_find_projection_by_name(): void
    {
        $projection = new Projection();

        $projection->createProjection('account', Status::IDLE);

        $this->assertNull($projection->findByName('deposits'));
        $this->assertInstanceOf(ProjectionModel::class, $projection->findByName('account'));
    }

    /**
     * @test
     */
    public function it_find_projection_by_sorting_names_ascendant(): void
    {
        $projection = new Projection();

        $projection->createProjection('account', Status::IDLE);
        $projection->createProjection('customer', Status::RUNNING);

        $this->assertEquals([], $projection->findByNames('deposits', 'withdraws'));
        $this->assertEquals(['account'], $projection->findByNames('deposits', 'account'));
        $this->assertEquals(['account', 'customer'], $projection->findByNames('customer', 'account'));
        $this->assertEquals(['account', 'customer'], $projection->findByNames('customer', 'deposits', 'account'));
    }

    /**
     * @test
     */
    public function it_delete_projection_by_name(): void
    {
        $projection = new Projection();

        $projection->createProjection('account', Status::IDLE);
        $projection->createProjection('customer', Status::RUNNING);

        $this->assertEquals(['account', 'customer'], $projection->findByNames('customer', 'account'));

        $this->assertTrue($projection->deleteProjectionByName('customer'));
        $this->assertNull($projection->findByName('customer'));

        $this->assertTrue($projection->deleteProjectionByName('account'));
        $this->assertNull($projection->findByName('account'));
    }

    /**
     * @test
     */
    public function it_always_acquire_lock_when_locked_until_is_null(): void
    {
        /** @var ProjectionModel|ProjectionProvider|Model $projection */
        $projection = new Projection();
        $projection->createProjection('account', Status::IDLE);

        $this->assertNull($projection->newQuery()->find(1)->lockedUntil());

        $now = UniversalPointInTime::now();
        $lockedUntil = $now->add('PT10S')->toString();

        $result = $projection->acquireLock('account', 'running', $lockedUntil, $now->toString());
        $this->assertTrue($result);

        /** @var ProjectionModel|ProjectionProvider|Model $updatedProjection */
        $updatedProjection = $projection->newQuery()->find(1);

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());
    }

    /**
     * @test
     */
    public function it_always_acquire_lock_when_locked_until_from_database_is_less_than_now(): void
    {
        $projection = new Projection();
        $projection->createProjection('account', Status::IDLE);

        $this->assertNull($projection->findByName('account')->lockedUntil());

        $now = UniversalPointInTime::now();
        $lockedUntil = $now->sub('PT10S')->toString();

        $result = $projection->acquireLock('account', 'running', $lockedUntil, $now->toString());
        $this->assertTrue($result);

        $updatedProjection = $projection->find(1);

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());

        $updatedLockedUntil = $now->add('PT10S')->toString();
        $result = $projection->acquireLock('account', 'running', $updatedLockedUntil, $now->toString());

        $updatedProjection = $projection->newQuery()->find(1);

        $this->assertTrue($result);
        $this->assertEquals($updatedLockedUntil, $updatedProjection->lockedUntil());
    }

    protected function getPackageProviders($app): array
    {
        return [ProjectorServiceProvider::class];
    }
}
