<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\Util;

use Ramsey\Uuid\Uuid;
use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Foundation\Clock\UniversalPointInTime;
use Chronhub\Foundation\Support\Contracts\Message\Header;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Balance;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountRegistered;

trait FeedChroniclerWithDeposits
{
    protected function feedEventStoreWithDeposits(int $limit = 10): void
    {
        $this->chronicler->persistFirstCommit(
            new Stream(new StreamName('transactions'))
        );

        $version = 0;
        $balance = 0;

        while (0 !== $limit) {
            ++$version;

            $headers = [
                Header::EVENT_ID          => Uuid::uuid4()->toString(),
                Header::EVENT_TYPE        => DepositMade::class,
                Header::EVENT_TIME        => (UniversalPointInTime::now()->toString()),
                Header::AGGREGATE_VERSION => $version,
                Header::INTERNAL_POSITION => $version,
                Header::AGGREGATE_ID      => $this->accountId->toString(),
                Header::AGGREGATE_ID_TYPE => AccountId::class,
            ];

            $balance = 1 === $version ? 0 : $balance + 100;

            $this->chronicler->persist(
                new Stream(new StreamName('transactions'), [
                    DepositMade::forUser(
                        $this->accountId,
                        $this->customerId,
                        100, Balance::startAt($balance)
                    )->withHeaders($headers),
                ])
            );

            --$limit;
        }
    }

    protected function feedEventStoreWithAccount(): void
    {
        $this->chronicler->persistFirstCommit(
            new Stream(new StreamName('account'))
        );

        $headers = [
                Header::EVENT_ID          => Uuid::uuid4()->toString(),
                Header::EVENT_TYPE        => AccountRegistered::class,
                Header::EVENT_TIME        => (UniversalPointInTime::now()->toString()),
                Header::AGGREGATE_VERSION => 1,
                Header::INTERNAL_POSITION => 1,
                Header::AGGREGATE_ID      => $this->accountId->toString(),
                Header::AGGREGATE_ID_TYPE => AccountId::class,
            ];

        $this->chronicler->persist(
                new Stream(new StreamName('account'), [
                    AccountRegistered::forUser(
                        $this->accountId,
                        $this->customerId,
                         Balance::startAtZero()
                    )->withHeaders($headers),
                ])
            );
    }
}
