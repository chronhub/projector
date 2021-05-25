<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional;

use Generator;
use Ramsey\Uuid\Uuid;
use Chronhub\Projector\Status;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

final class StopProjectionFromManagerTest extends TestCaseWithOrchestra
{
    /**
     * @test
     */
    public function it_stop_projection(): void
    {
        $streamName = new StreamName('account');

        /** @var AggregateId|AccountId $aggregateId */
        $accountId = AccountId::create();

        /** @var Chronicler $chronicler */
        $chronicler = Chronicle::create('in_memory');

        $chronicler->persistFirstCommit(new Stream($streamName));
        $chronicler->persist(new Stream($streamName, $this->provideEvents($accountId, 10)));

        $test = $this;

        $projector = Project::create('in_memory');
        $projection = $projector->createProjection('account');
        $projection
            ->initialize(fn (): array => ['balance' => 0])
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition())
            ->fromStreams('account')
            ->whenAny(function (DepositMade $event, array $state) use ($projector, $test): array {
                if (900 === $state['balance']) {
                    $projector->stop('account');

                    $test->assertEquals(Status::STOPPING, $projector->statusOf('account'));

                    return $state;
                }

                $state['balance'] += $event->deposit();

                return $state;
            })
            ->run(false);

        $this->assertEquals(['balance' => 900], $projector->stateOf('account'));
        $this->assertEquals(Status::IDLE, $projector->statusOf('account'));
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
