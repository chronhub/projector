<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Ramsey\Uuid\Uuid;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Model\InMemoryProjection;
use Chronhub\Projector\ProjectorServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Context\ContextualReadModel;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Aggregate\GenericAggregateId;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Support\ReadModel\InMemoryReadModel;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;
use function array_values;

final class RunReadModelTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_run_projection_once(): void
    {
        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
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
        $this->feedEventStore();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualReadModel $this */
                $test->assertEquals('account_stream', $this->streamName());

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
        $this->assertEquals('{"account_stream":10}', $model->position());
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
        $this->feedEventStore();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state) use ($test): array {
                    /* @var ContextualReadModel $this */
                    $test->assertEquals('account_stream', $this->streamName());

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
        $this->assertEquals('{"account_stream":10}', $model->position());
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
        $this->feedEventStore();

        $readModel = new InMemoryReadModel();

        $projection = $this->projector->createReadModelProjection('deposits', $readModel);

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualReadModel $this */
                $test->assertEquals('account_stream', $this->streamName());

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
        $this->assertEquals('{"account_stream":5}', $model->position());
        $this->assertEquals('idle', $model->status());
        $this->assertEquals('{"events":5,"deposits":500}', $model->state());
        $this->assertNull($model->lockedUntil());

        $this->assertEquals(['events' => 5, 'deposits' => 500], $projection->getState());

        $container = array_values($readModel->getContainer())[0];

        $this->assertEquals(500, $container['balance']);
    }

    private function feedEventStore(): void
    {
        $this->chronicler->persistFirstCommit(
            new Stream(new StreamName('account_stream'))
        );

        $i = 10;
        $version = 0;
        /** @var AggregateId|AccountId $accountId */
        $accountId = AccountId::create();
        /** @var AggregateId|CustomerId $customerId */
        $customerId = CustomerId::create();
        $balance = 0;

        while (0 !== $i) {
            ++$version;

            $headers = [
                Header::EVENT_ID          => Uuid::uuid4()->toString(),
                Header::EVENT_TYPE        => DepositMade::class,
                Header::EVENT_TIME        => (UniversalPointInTime::now()->toString()),
                Header::AGGREGATE_VERSION => $version,
                Header::INTERNAL_POSITION => $version,
                Header::AGGREGATE_ID      => $accountId->toString(),
                Header::AGGREGATE_ID_TYPE => GenericAggregateId::class,
            ];

            $oldBalance = 1 === $version ? 0 : $balance + 100;

            $this->chronicler->persist(
                new Stream(new StreamName('account_stream'), [
                    DepositMade::forUser($accountId, $customerId, 100, Balance::startAt($oldBalance))
                        ->withHeaders($headers),
                ])
            );

            $balance = $oldBalance;
            --$i;
        }
    }

    private Chronicler $chronicler;
    private ProjectionProvider $projectionProvider;
    private Manager $projector;

    public function defineEnvironment($app): void
    {
        $this->setupChronicler($app);

        $this->projector = Project::create('in_memory');
    }

    private function setupChronicler(Application $app): void
    {
        $projectionProvider = $app->make(InMemoryProjectionProvider::class);

        $this->projectionProvider = $app->instance(
            InMemoryProjectionProvider::class, $projectionProvider
        );

        $app->singleton(InMemoryEventStream::class);

        $this->chronicler = Chronicle::create('in_memory');
    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
