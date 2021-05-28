<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

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
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Context\ContextualProjection;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Aggregate\GenericAggregateId;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

final class RunProjectionTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_run_projection_once(): void
    {
        $test = $this;

        $this->feedEventStore(1);

        $projection = $this->projector->createProjection('deposits');
        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->whenAny(function (AggregateChanged $event, array $state) use ($test): void {
                /* @var ContextualProjection $this */

                $test->assertInstanceOf(DepositMade::class, $event);
                $test->assertEquals([], $state);

                $test->assertInstanceOf(Clock::class, $this->clock());
                $test->assertEquals('account_stream', $this->streamName());
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

        $projection = $this->projector->createProjection('deposits');

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualProjection $this * */
                $test->assertEquals('account_stream', $this->streamName());

                ++$state['events'];
                $state['deposits'] += $event->deposit();

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
    }

    /**
     * @test
     */
    public function it_handle_events_with_array(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createProjection('deposits');

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state) use ($test): array {
                    /* @var ContextualProjection $this * */
                    $test->assertEquals('account_stream', $this->streamName());

                    ++$state['events'];
                    $state['deposits'] += $event->deposit();

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
    }

    /**
     * @test
     */
    public function it_can_stop_projection_inside_event_handler(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createProjection('deposits');

        $test = $this;

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->initialize(fn (): array => ['events' => 0, 'deposits' => 0])
            ->fromStreams('account_stream')
            ->whenAny(function (DepositMade $event, array $state) use ($test): array {
                /* @var ContextualProjection $this */

                $test->assertEquals('account_stream', $this->streamName());

                ++$state['events'];
                $state['deposits'] += $event->deposit();

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
    }

    private function feedEventStore(int $limit = 10): void
    {
        $this->chronicler->persistFirstCommit(
            new Stream(new StreamName('account_stream'))
        );

        $version = 0;
        /** @var AggregateId|AccountId $accountId */
        $accountId = AccountId::create();
        /** @var AggregateId|CustomerId $customerId */
        $customerId = CustomerId::create();
        $balance = 0;

        while (0 !== $limit) {
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

            --$limit;
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
