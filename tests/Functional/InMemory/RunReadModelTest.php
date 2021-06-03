<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Chronhub\Projector\Model\InMemoryProjection;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Projector\Context\ContextualReadModel;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Support\ReadModel\InMemoryReadModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Tests\Functional\Util\SetupInMemoryChronicler;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Projector\Tests\Functional\Util\FeedChroniclerWithDeposits;
use function array_values;

final class RunReadModelTest extends TestCaseWithOrchestra
{
    use SetupInMemoryChronicler;
    use FeedChroniclerWithDeposits;

    /**
     * @test
     */
    public function it_run_projection_once(): void
    {
        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (AggregateChanged $event, array $state): void {
                /* @var ContextualReadModel $this */
            })
            ->run(false);

        $this->assertEquals([], $projection->getState());
    }

    /**
     * @test
     */
    public function it_handle_events_with_closure(): void
    {
        $this->feedEventStoreWithDeposits();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualReadModel $this */
                $test->assertEquals('transactions', $this->streamName());

                ++$state['events'];
                $state['deposits'] += $event->deposit();

                if (1 === $state['events']) {
                    $this->readModel()->stack('insert', $event->aggregateId(), [
                        'customer_id' => $event->customerId()->toString(),
                        'balance'     => $event->oldBalance()->available() + $event->deposit(),
                    ]);
                } else {
                    $this->readModel()->stack(
                        'update',
                        $event->aggregateId(),
                        'balance',
                        $event->oldBalance()->available() + $event->deposit()
                    );
                }

                return $state;
            })->run(false);

        /** @var InMemoryProjection $model */
        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());

        $container = array_values($readModel->getContainer())[0];

        $this->assertEquals(1000, $container['balance']);
    }

    /**
     * @test
     */
    public function it_handle_events_with_array(): void
    {
        $this->feedEventStoreWithDeposits();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state) use ($test): array {
                    /* @var ContextualReadModel $this */
                    $test->assertEquals('transactions', $this->streamName());

                    ++$state['events'];
                    $state['deposits'] += $event->deposit();

                    if (1 === $state['events']) {
                        $this->readModel()->stack('insert', $event->aggregateId(), [
                            'customer_id' => $event->customerId()->toString(),
                            'balance'     => $event->oldBalance()->available() + $event->deposit(),
                        ]);
                    } else {
                        $this->readModel()->stack(
                            'increment',
                            $event->aggregateId(),
                            'balance',
                            $event->deposit()
                        );
                    }

                    return $state;
                },
            ])->run(false);

        /** @var InMemoryProjection $model */
        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":10}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":10,"deposits":1000}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 10, 'deposits' => 1000], $projection->getState());

        $container = array_values($readModel->getContainer())[0];

        $this->assertEquals(1000, $container['balance']);
    }

    /**
     * @test
     */
    public function it_can_stop_projection_inside_event_handler(): void
    {
        $this->feedEventStoreWithDeposits();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('transactions')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualReadModel $this */
                $test->assertEquals('transactions', $this->streamName());

                ++$state['events'];
                $state['deposits'] += $event->deposit();

                if (1 === $state['events']) {
                    $this->readModel()->stack('insert', $event->aggregateId(), [
                        'customer_id' => $event->customerId()->toString(),
                        'balance'     => $event->oldBalance()->available() + $event->deposit(),
                    ]);
                } else {
                    $this->readModel()->stack(
                        'update',
                        $event->aggregateId(),
                        'balance',
                        $event->oldBalance()->available() + $event->deposit()
                    );
                }

                if (500 === $state['deposits']) {
                    $this->stop();
                }

                return $state;
            })->run(false);

        /** @var InMemoryProjection $model */
        $model = $this->projectionProvider->findByName('deposits');

        $this->assertInstanceOf(ProjectionModel::class, $model);

        $this->assertEquals('deposits', $model->name());
        $this->assertEquals('{"transactions":5}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":5,"deposits":500}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 5, 'deposits' => 500], $projection->getState());

        $container = array_values($readModel->getContainer())[0];

        $this->assertEquals(500, $container['balance']);
    }
}
