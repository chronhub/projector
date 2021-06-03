<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Generator;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Context\ContextualProjection;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Support\Contracts\ProjectorFactory;
use Chronhub\Projector\Support\Contracts\PersistentProjector;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Tests\Functional\Util\SetupInMemoryChronicler;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Projector\Tests\Functional\Util\FeedChroniclerWithDeposits;

final class RunProjectionTest extends TestCaseWithOrchestra
{
    use SetupInMemoryChronicler;
    use FeedChroniclerWithDeposits;

    /**
     * @test
     */
    public function it_run(): void
    {
        /** @var PersistentProjector|ProjectorFactory $projection */
        $projection = $this->projector->createProjection('customer_stream');
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['called' => false])
            ->fromStreams('customer')
            ->whenAny(function (AggregateChanged $event, array $state): array {
                $state['called'] = true;

                return $state;
            })->run(false);

        $this->assertFalse($projection->getState()['called']);
        $this->assertEquals('customer_stream', $projection->getStreamName());
    }

    /**
     * @test
     * @dataProvider provideTimer
     */
    public function it_run_with_timer(int|string $timer): void
    {
        /** @var PersistentProjector|ProjectorFactory $projection */
        $projection = $this->projector->createProjection('customer_stream');
        $projection
            ->until($timer)
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['called' => false])
            ->fromStreams('customer')
            ->whenAny(function (AggregateChanged $event, array $state): array {
                $state['called'] = true;

                return $state;
            })->run(true);

        $this->assertFalse($projection->getState()['called']);
        $this->assertEquals('customer_stream', $projection->getStreamName());
    }

    /**
     * @test
     */
    public function it_run_once(): void
    {
        $test = $this;

        $this->feedEventStoreWithDeposits(2);

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->initialize(fn (): array => ['called' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('transactions')
            ->whenAny(function (AggregateChanged $event, array $state) use ($test): array {
                /* @var ContextualProjection $this */
                $test->assertEquals('transactions', $this->streamName());
                $test->assertInstanceOf(Clock::class, $this->clock());
                $test->assertInstanceOf(DepositMade::class, $event);

                ++$state['called'];

                return $state;
            })
            ->run(false);

        $this->assertEquals(['called' => 2], $projection->getState());
    }

    /**
     * @test
     */
    public function it_handle_events_with_closure(): void
    {
        $this->feedEventStoreWithDeposits();

        $this->assertNull($this->projectionProvider->findByName('deposits'));

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state): array {
                /* @var ContextualProjection $this * */
                ++$state['events'];
                $state['deposits'] += $event->deposit();

                return $state;
            })->run(false);

        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());
    }

    /**
     * @test
     */
    public function it_handle_events_with_array(): void
    {
        $this->feedEventStoreWithDeposits(10);

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    ++$state['events'];
                    $state['deposits'] += $event->deposit();

                    return $state;
                },
            ])->run(false);

        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());
    }

    /**
     * @test
     */
    public function it_can_stop_projection_inside_event_handler(): void
    {
        $this->feedEventStoreWithDeposits(10);

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state): array {
                /* @var ContextualProjection $this */
                ++$state['events'];
                $state['deposits'] += $event->deposit();

                if (500 === $state['deposits']) {
                    $this->stop();
                }

                return $state;
            })->run(false);

        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":5}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":5,"deposits":500}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 5, 'deposits' => 500], $projection->getState());
    }

    /**
     * @test
     */
    public function it_can_emit_event(): void
    {
        $this->feedEventStoreWithDeposits(10);

        $projection = $this->projector->createProjection('transactions');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event): void {
                /* @var ContextualProjection $this */
                $this->emit($event);
            })->run(false);

        $model = $this->projectionProvider->findByName('transactions');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('transactions', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals([], $projection->getState());
    }

    /**
     * @test
     */
    public function it_can_link_event_to_a_new_stream(): void
    {
        $this->feedEventStoreWithDeposits(10);

        $this->assertFalse($this->chronicler->hasStream(new StreamName('deposits')));

        $projection = $this->projector->createProjection('transactions');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event): void {
                /* @var ContextualProjection $this */
                $this->linkTo('deposits', $event);
            })->run(false);

        $model = $this->projectionProvider->findByName('transactions');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('transactions', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals([], $projection->getState());

        $this->assertTrue($this->chronicler->hasStream(new StreamName('deposits')));
    }

    /**
     * @test
     */
    public function it_reset_projection(): void
    {
        $this->feedEventStoreWithDeposits();

        $this->assertNull($this->projectionProvider->findByName('deposits'));

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state): array {
                /* @var ContextualProjection $this * */
                ++$state['events'];
                $state['deposits'] += $event->deposit();

                return $state;
            })->run(false);

        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);
        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());

        $projection->reset();

        $resetModel = $this->projectionProvider->findByName('deposits');

        $this->assertEquals('{}', $resetModel->position());
        $this->assertEquals('idle', $resetModel->status());
        $this->assertEquals('{"events":0,"deposits":0}', $resetModel->state());
        $this->assertNull($model->lockedUntil());

        $projection->run(false);

        $rerunModel = $this->projectionProvider->findByName('deposits');

        $this->assertEquals('deposits', $rerunModel->name());
        $this->assertEquals('{"transactions":10}', $rerunModel->position());
        $this->assertEquals('idle', $rerunModel->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $rerunModel->state());
        $this->assertNull($rerunModel->lockedUntil());
    }

    /**
     * @test
     */
    public function it_delete_projection(): void
    {
        $this->feedEventStoreWithDeposits();

        $this->assertNull($this->projectionProvider->findByName('deposits'));

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state): array {
                /* @var ContextualProjection $this * */
                ++$state['events'];
                $state['deposits'] += $event->deposit();

                return $state;
            })->run(false);

        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);
        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());
        $this->assertFalse($this->chronicler->hasStream(new StreamName('deposits')));

        /* @var PersistentProjector $projection */
        $projection->delete(false);

        $resetModel = $this->projectionProvider->findByName('deposits');

        $this->assertNull($resetModel);

        $projection->run(false);

        $rerunModel = $this->projectionProvider->findByName('deposits');

        $this->assertEquals('deposits', $rerunModel->name());
        $this->assertEquals('{"transactions":10}', $rerunModel->position());
        $this->assertEquals('idle', $rerunModel->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $rerunModel->state());
        $this->assertNull($rerunModel->lockedUntil());
    }

    /**
     * @test
     */
    public function it_delete_projection_with_emitted_events(): void
    {
        $this->feedEventStoreWithDeposits(10);

        $this->assertFalse($this->chronicler->hasStream(new StreamName('deposits')));

        $projection = $this->projector->createProjection('transactions');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event): void {
                /* @var ContextualProjection $this */
                $this->linkTo('deposits', $event);
            })->run(false);

        $model = $this->projectionProvider->findByName('transactions');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('transactions', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals([], $projection->getState());
        $this->assertTrue($this->chronicler->hasStream(new StreamName('deposits')));

        /* @var PersistentProjector $projection */
        $projection->delete(true);
        $this->assertNull($this->projectionProvider->findByName('transactions'));

        // linked projection need to be handle by dev
        $this->assertTrue($this->chronicler->hasStream(new StreamName('deposits')));

        $this->chronicler->delete(new StreamName('deposits'));
        $this->assertFalse($this->chronicler->hasStream(new StreamName('deposits')));
    }

    /**
     * @test
     */
    public function it_run_projection_from_all_streams(): void
    {
        $test = $this;
        $this->feedEventStoreWithAccount();
        $this->feedEventStoreWithDeposits(2);

        $projection = $this->projector->createProjection('deposits');

        $projection
            ->initialize(fn (): array => ['called' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromAll()
            ->whenAny(function (AggregateChanged $event, array $state) use ($test): array {
                /* @var ContextualProjection $this */
                $test->assertContains($this->streamName(), ['account', 'transactions']);

                ++$state['called'];

                return $state;
            })
            ->run(false);

        $this->assertEquals(['called' => 3], $projection->getState());
    }

    public function provideTimer(): Generator
    {
        yield [1];
        yield ['PT1S'];
    }
}
