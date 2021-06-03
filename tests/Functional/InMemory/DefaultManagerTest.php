<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Generator;
use Ramsey\Uuid\Uuid;
use Chronhub\Projector\Status;
use Chronhub\Projector\ProjectQuery;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Projector\DefaultManager;
use Chronhub\Projector\ProjectReadModel;
use Chronhub\Projector\ProjectProjection;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Exception\ProjectionNotFound;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Support\ReadModel\InMemoryReadModel;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Projector\Support\Scope\InMemoryProjectionQueryScope;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

final class DefaultManagerTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_assert_instance(): void
    {
        $projector = Project::create('in_memory');

        $this->assertInstanceOf(DefaultManager::class, $projector);
    }

    /**
     * @test
     */
    public function it_create_projection(): void
    {
        $projector = Project::create('in_memory');

        $projection = $projector->createProjection('account');

        $this->assertInstanceOf(ProjectProjection::class, $projection);
    }

    /**
     * @test
     */
    public function it_create_read_model_projection(): void
    {
        $projector = Project::create('in_memory');

        $projection = $projector->createReadModelProjection('account', new InMemoryReadModel());

        $this->assertInstanceOf(ProjectReadModel::class, $projection);
    }

    /**
     * @test
     */
    public function it_create_query(): void
    {
        $projector = Project::create('in_memory');

        $projection = $projector->createQuery();

        $this->assertInstanceOf(ProjectQuery::class, $projection);
    }

    /**
     * @test
     */
    public function it_access_query_scope(): void
    {
        $projector = Project::create('in_memory');

        $this->assertInstanceOf(InMemoryProjectionQueryScope::class, $projector->queryScope());
    }

    /**
     * @test
     */
    public function it_fetch_status_of_projection(): void
    {
        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));
        $this->assertEmpty($projector->filterNamesOf('account'));

        try {
            $projector->statusOf('account');
        } catch (ProjectionNotFound) {
        }

        $projection = $projector->createProjection('account');

        $projection
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('customer')
            ->whenAny(function (): void { })
            ->run(false);

        $this->assertTrue($projector->exists('account'));

        $this->assertEquals($projector->statusOf('account'), Status::IDLE);
        $this->assertEquals(['account'], $projector->filterNamesOf('account'));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_fetching_status_of_not_found_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));

        $projector->statusOf('account');
    }

    /**
     * @test
     */
    public function it_fetch_stream_positions_of_projection(): void
    {
        $streamName = new StreamName('account');

        /** @var AggregateId|AccountId $aggregateId */
        $accountId = AccountId::create();

        /** @var Chronicler $chronicler */
        $chronicler = Chronicle::create('in_memory');

        $chronicler->persistFirstCommit(new Stream($streamName));
        $chronicler->persist(new Stream($streamName, $this->provideEvents($accountId, 10)));

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));
        $this->assertEmpty($projector->filterNamesOf('account'));

        $projection = $projector->createProjection('account');
        $projection
            ->initialize(fn (): array => ['balance' => 0])
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (DepositMade $event, array $state): array {
                $state['balance'] += $event->deposit();

                return $state;
            })
            ->run(false);

        $this->assertEquals(['account' => 10], $projector->streamPositionsOf('account'));
        $this->assertEquals(1000, $projection->getState()['balance']);
        $this->assertEquals(['account'], $projector->filterNamesOf('account'));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_fetching_stream_positions_of_not_found_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));

        $projector->streamPositionsOf('account');
    }

    /**
     * @test
     */
    public function it_fetch_state_of_projection(): void
    {
        $streamName = new StreamName('account');

        /** @var AggregateId|AccountId $aggregateId */
        $accountId = AccountId::create();

        /** @var Chronicler $chronicler */
        $chronicler = Chronicle::create('in_memory');

        $chronicler->persistFirstCommit(new Stream($streamName));
        $chronicler->persist(new Stream($streamName, $this->provideEvents($accountId, 10)));

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));
        $this->assertEmpty($projector->filterNamesOf('account'));

        $projection = $projector->createProjection('account');
        $projection
            ->initialize(fn (): array => ['balance' => 0])
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (DepositMade $event, array $state): array {
                $state['balance'] += $event->deposit();

                return $state;
            })
            ->run(false);

        $this->assertEquals(['balance' => 1000], $projector->stateOf('account'));
        $this->assertEquals(1000, $projection->getState()['balance']);
        $this->assertEquals(['account'], $projector->filterNamesOf('account'));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_fetching_state_of_not_found_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));

        $projector->stateOf('account');
    }

    private function provideEvents(AggregateId|AccountId $aggregateId, int $limit): Generator
    {
        /** @var AggregateId|CustomerId $customerId */
        $customerId = CustomerId::create();

        $headers = [
            Header::EVENT_ID          => Uuid::uuid4()->toString(),
            Header::EVENT_TYPE        => DepositMade::class,
            Header::EVENT_TIME        => UniversalPointInTime::now()->toString(),
            Header::AGGREGATE_ID      => $aggregateId->toString(),
            Header::AGGREGATE_ID_TYPE => AccountId::class,
        ];

        $balance = 0;
        $version = 1;

        while (0 !== $limit) {
            $event = DepositMade::forUser($aggregateId, $customerId, 100, Balance::startAt($balance));

            yield $event->withHeaders(
                $headers + [
                    Header::INTERNAL_POSITION => $version,
                    Header::AGGREGATE_VERSION => $version,
                ]
            );

            $balance += 100;
            ++$version;

            --$limit;
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->singleton(InMemoryEventStream::class);
    }
}
