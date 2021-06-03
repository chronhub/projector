<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Ramsey\Uuid\Uuid;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Context\ContextualQuery;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Aggregate\GenericAggregateId;
use Chronhub\Foundation\Support\Contracts\Clock\Clock;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

final class RunQueryProjectionTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_run_projection_once(): void
    {
        $test = $this;

        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['count' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->whenAny(function (AggregateChanged $event, array $state) use ($test): void {
                /* @var ContextualQuery $this */
                $test->assertEquals('account_stream', $this->streamName());
                $test->assertEquals(['count' => 0], $state);
                $test->assertInstanceOf(Clock::class, $this->clock());
            })
            ->run(false);

        $this->assertEquals(['count' => 0], $projection->getState());
    }

    /**
     * @test
     */
    public function it_handle_events_with_closure(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->whenAny(function (DepositMade $event, array $state): array {
                /* @var ContextualQuery $this */
                $state['deposits'] += $event->deposit();

                return $state;
            })
            ->run(false);

        $this->assertEquals(['deposits' => 1000], $projection->getState());
    }

    /**
     * @test
     */
    public function it_handle_events_with_array(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    /* @var ContextualQuery $this */
                    $state['deposits'] += $event->deposit();

                    return $state;
                },
            ])
            ->run(false);

        $this->assertEquals(['deposits' => 1000], $projection->getState());
    }

    /**
     * @test
     */
    public function it_can_stop_projection_inside_event_handler(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    /* @var ContextualQuery $this */
                    $state['deposits'] += $event->deposit();

                    if (500 === $state['deposits']) {
                        $this->stop();
                    }

                    return $state;
                },
            ])
            ->run(false);

        $this->assertEquals(['deposits' => 500], $projection->getState());
    }

    /**
     * @test
     */
    public function it_can_run_with_timer_as_seconds(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    /* @var ContextualQuery $this */
                    $state['deposits'] += $event->deposit();

                    return $state;
                },
            ])
            ->until(1)
            ->run(true);

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_can_run_with_timer_as_string_interval(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    /* @var ContextualQuery $this */
                    $state['deposits'] += $event->deposit();

                    return $state;
                },
            ])
            ->until('PT1S')
            ->run(true);

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_can_reset_query(): void
    {
        $this->feedEventStore();

        $projection = $this->projector->createQuery();
        $projection
            ->initialize(fn (): array => ['deposits' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account_stream')
            ->when([
                'deposit-made' => function (DepositMade $event, array $state): array {
                    /* @var ContextualQuery $this */
                    $state['deposits'] += $event->deposit();

                    return $state;
                },
            ])
            ->run(false);

        $this->assertEquals(['deposits' => 1000], $projection->getState());

        $projection->reset();

        $this->assertEquals(['deposits' => 0], $projection->getState());
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

            --$i;
        }
    }

    private Chronicler $chronicler;
    private Manager $projector;

    public function defineEnvironment($app): void
    {
        $this->chronicler = Chronicle::create('in_memory');

        $this->projector = Project::create('in_memory');
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
