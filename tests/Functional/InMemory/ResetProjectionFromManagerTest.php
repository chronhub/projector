<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\InMemory;

use Generator;
use Ramsey\Uuid\Uuid;
use Chronhub\Projector\Status;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Chronicler\Exception\StreamNotFound;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Support\Contracts\Projector;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Context\ContextualProjection;
use Chronhub\Projector\Exception\ProjectionNotFound;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Projector\Exception\ProjectionAlreadyRunning;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Chronicler\Support\Contracts\Factory\ChroniclerManager;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;
use function usleep;

final class ResetProjectionFromManagerTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_reset_projection(): void
    {
        $this->expectException(StreamNotFound::class);

        $accountStreamName = new StreamName('account');

        /** @var AggregateId|AccountId $aggregateId */
        $accountId = AccountId::create();

        /** @var Chronicler $chronicler */
        $chronicler = Chronicle::create('in_memory');

        $chronicler->persistFirstCommit(new Stream($accountStreamName));
        $chronicler->persist(new Stream($accountStreamName, $this->provideEvents($accountId, 10)));

        $projector = Project::create('in_memory');
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
        $this->assertEquals(['account' => 10], $projector->streamPositionsOf('account'));
        $this->assertEquals(Status::IDLE, $projector->statusOf('account'));

        $projector->reset('account');

        $this->assertEquals(Status::RESETTING, $projector->statusOf('account'));
        $this->assertEquals(['balance' => 1000], $projector->stateOf('account'));
        $this->assertEquals(['account' => 10], $projector->streamPositionsOf('account'));

        $this->reRunProjection($projection);

        $this->assertEquals(['balance' => 0], $projector->stateOf('account'));
        $this->assertEquals(Status::IDLE, $projector->statusOf('account'));
        $this->assertEquals(['account' => 0], $projector->streamPositionsOf('account'));

        $this->app[ChroniclerManager::class]
            ->create('in_memory')
            ->retrieveAll($accountStreamName, $accountId)->current();
    }

    /**
     * @test
     */
    public function it_reset_projection_linking_event_to_new_stream(): void
    {
        // setup events
        $accountStreamName = new StreamName('account');
        $depositStreamName = new StreamName('deposit');

        /** @var AggregateId|AccountId $aggregateId */
        $accountId = AccountId::create();

        /** @var Chronicler $chronicler */
        $chronicler = Chronicle::create('in_memory');

        $chronicler->persistFirstCommit(new Stream($accountStreamName));
        $chronicler->persist(new Stream($accountStreamName, $this->provideEvents($accountId, 10)));

        // setup projection
        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('account'));
        $this->assertFalse($projector->exists('deposit'));
        $this->assertFalse($this->app[InMemoryEventStream::class]->hasRealStreamName('deposit'));

        $projection = $projector->createProjection('account');
        $projection
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (DepositMade $event): void {
                /* @var ContextualProjection $this */
                $this->linkTo('deposit', $event);
            })
            ->run(false);

        $this->assertTrue($projector->exists('account'));
        $this->assertTrue($this->app[InMemoryEventStream::class]->hasRealStreamName('deposit'));

        // run linked projection
        $depositProjection = $projector->createProjection('deposit');
        $depositProjection
            ->initialize(fn (): array => ['count' => 0])
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (DepositMade $event, array $state): array {
                ++$state['count'];

                return $state;
            })
            ->run(false);

        $this->assertTrue($projector->exists('deposit'));
        $this->assertTrue($this->app[InMemoryEventStream::class]->hasRealStreamName('deposit'));

        $this->assertTrue(Chronicle::create('in_memory')->hasStream($accountStreamName));
        $this->assertTrue(Chronicle::create('in_memory')->hasStream($depositStreamName));

        // reset projection
        $depositProjection->reset();

        $this->assertEquals(['count' => 0], $projector->stateOf('deposit'));
        $this->assertEquals(Status::IDLE, $projector->statusOf('deposit'));
        $this->assertEmpty($projector->streamPositionsOf('deposit'));

        $this->reRunProjection($depositProjection);

        $this->assertEquals(['count' => 10], $projector->stateOf('deposit'));
        $this->assertEquals(Status::IDLE, $projector->statusOf('deposit'));
        $this->assertEquals(['account' => 10], $projector->streamPositionsOf('deposit'));

        $this->assertTrue(Chronicle::create('in_memory')->hasStream($accountStreamName));
        $this->assertFalse(Chronicle::create('in_memory')->hasStream($depositStreamName));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_resetting_not_found_projection(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $projector = Project::create('in_memory');

        $this->assertFalse($projector->exists('customer'));

        $projector->reset('customer');
    }

    private function reRunProjection(Projector $projection): void
    {
        do {
            $exception = null;

            try {
                $projection->run(false);
            } catch (ProjectionAlreadyRunning $e) {
                $exception = $e;
                usleep(1000);
            }
        } while (null !== $exception);
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
        $app->singleton(InMemoryProjectionProvider::class);
    }
}
