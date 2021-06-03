<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Functional\Util;

use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\ProjectorServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Chronicler\Support\Facade\Chronicle;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;

trait SetupInMemoryChronicler
{
    private Chronicler $chronicler;
    private ProjectionProvider|InMemoryProjectionProvider $projectionProvider;
    private Manager $projector;
    private AccountId|AggregateId $accountId;
    private CustomerId|AggregateId $customerId;

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->customerId = CustomerId::create();
        $this->accountId = AccountId::create();
    }

    protected function defineEnvironment($app): void
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
}
