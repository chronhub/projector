<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Util;

use Ramsey\Uuid\Uuid;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Aggregate\GenericAggregateId;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

trait FeedChroniclerWithDeposits
{
    private Chronicler $chronicler;
    private ProjectionProvider|InMemoryProjectionProvider $projectionProvider;
    private Manager $projector;

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

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

        $app->instance(Chronicler::class, $this->chronicler);
    }

    protected function feedEventStoreWithDeposits(int $limit = 10): void
    {
        $this->chronicler->persistFirstCommit(
            new Stream(new StreamName('account_stream'))
        );

        $version = 0;
        $balance = 0;

        /** @var AggregateId|AccountId $accountId */
        $accountId = AccountId::create();

        /** @var AggregateId|CustomerId $customerId */
        $customerId = CustomerId::create();

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
}
