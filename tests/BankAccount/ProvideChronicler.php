<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\BankAccount;

use Chronhub\Chronicler\Stream\Stream;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Foundation\Reporter\ReportEvent;
use Chronhub\Foundation\Reporter\ReportQuery;
use Chronhub\Foundation\Reporter\ReportCommand;
use Chronhub\Foundation\Support\Facade\Publish;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Chronicler\Support\Contracts\Chronicler;
use Chronhub\Foundation\Exception\MessageDispatchFailed;
use Chronhub\Foundation\Reporter\Subscribers\HandleEvent;
use Chronhub\Chronicler\Factory\ChroniclerServiceProvider;
use Chronhub\Chronicler\Driver\InMemory\InMemoryChronicler;
use Chronhub\Foundation\Reporter\Subscribers\HandleCommand;
use Chronhub\Chronicler\Driver\InMemory\InMemoryEventStream;
use Chronhub\Foundation\Support\Contracts\Reporter\Reporter;
use Chronhub\Foundation\Support\Contracts\Aggregate\AggregateId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\Account;
use Chronhub\Foundation\Support\Contracts\Reporter\ReporterManager;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountId;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\Customer;
use Chronhub\Chronicler\Support\Contracts\Factory\ChroniclerManager;
use Chronhub\Chronicler\Support\Contracts\Factory\RepositoryManager;
use Chronhub\Foundation\Reporter\Services\FoundationServiceProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\MakeDeposit;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerId;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\MakeWithdraw;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\AccountCollection;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\RegisterCustomer;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\MakeDepositHandler;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\MakeWithdrawHandler;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\RegisterBankAccount;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\CustomerCollection;
use Chronhub\Chronicler\Support\BankAccount\Infrastructure\AccountChronicleStore;
use Chronhub\Chronicler\Support\BankAccount\Infrastructure\CustomerChronicleStore;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\RegisterCustomerHandler;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\RegisterBankAccountHandler;
use Chronhub\Chronicler\Support\BankAccount\Model\Customer\ChangeCustomerNameHandler;

trait ProvideChronicler
{
    protected Chronicler|InMemoryChronicler $chronicler;
    protected StreamName $customerStream;
    protected StreamName $accountStream;
    protected CustomerId|AggregateId $customerId;
    protected AccountId|AggregateId $accountId;

    protected function getPackageProviders($app): array
    {
        return [
            FoundationServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->customerStream = new StreamName('customer');
        $this->accountStream = new StreamName('account');

        $this->customerId = CustomerId::create();
        $this->accountId = AccountId::create();

        $this->setupReporterConfiguration($app);

        $this->registerDefaultReporters($app);

        $this->defineChronicler($app);

        $this->defineRepositoriesConfig($app);

        $this->seedChronicler();
    }

    protected function defineChronicler(Application $app): void
    {
        $app['config']->set('chronicler.connections',
            [
                'in_memory' => [
                    'driver'   => 'in_memory',
                    'strategy' => 'single',
                    'provider' => 'in_memory',
                    'options'  => false,
                ],
            ]
        );

        $app->singleton(InMemoryEventStream::class);

        $app->singleton(Chronicler::class, function (Application $app): Chronicler {
            return $app[ChroniclerManager::class]->create('in_memory');
        });

        $app->singleton(CustomerCollection::class, function (Application $app): CustomerCollection {
            $repository = $app[RepositoryManager::class]->create($this->customerStream->toString());

            return new CustomerChronicleStore($repository);
        });

        $app->singleton(AccountCollection::class, function (Application $app): AccountCollection {
            $repository = $app[RepositoryManager::class]->create($this->accountStream->toString());

            return new AccountChronicleStore($repository);
        });

        $this->chronicler = $app[Chronicler::class];
    }

    protected function defineRepositoriesConfig(Application $app): void
    {
        $app['config']->set('chronicler.repositories.' . $this->customerStream->toString(), [
            'aggregate_type'   => Customer::class,
            'chronicler'       => 'in_memory',
            'event_decorators' => [],
        ]);

        $app['config']->set('chronicler.repositories.' . $this->accountStream->toString(), [
            'aggregate_type'   => Account::class,
            'chronicler'       => 'in_memory',
            'event_decorators' => [],
        ]);
    }

    protected function setupReporterConfiguration(Application $app): void
    {
        $app['config']->set('reporter.reporting.command.default', [
            'handler_method' => 'command',
            'messaging'      => [
                'decorators'  => [],
                'subscribers' => [
                    HandleCommand::class,
                ],
            ],
            'map'            => [
                'register-customer'     => RegisterCustomerHandler::class,
                'register-bank-account' => RegisterBankAccountHandler::class,
                'make-deposit'          => MakeDepositHandler::class,
                'make-withdraw'         => MakeWithdrawHandler::class,
                'change-customer-name'  => ChangeCustomerNameHandler::class,
            ],
        ]);

        $app['config']->set('reporter.reporting.event.default', [
            'handler_method' => 'onEvent',
            'messaging'      => [
                'subscribers' => [HandleEvent::class],
            ],
            'map'            => [
                'customer-registered'   => [],
                'customer-name-changed' => [],
                'account-registered'    => [],
                'deposit-made'          => [],
                'withdraw-made'         => [],
            ],
        ]);
    }

    protected function registerDefaultReporters(Application $app): void
    {
        $app->bind(ReportCommand::class,
            fn (Application $app): Reporter => $app[ReporterManager::class]->command());

        $app->bind(ReportEvent::class,
            fn (Application $app): Reporter => $app[ReporterManager::class]->event());

        $app->bind(ReportQuery::class,
            fn (Application $app): Reporter => $app[ReporterManager::class]->query());
    }

    protected function seedChronicler(): void
    {
        $this->chronicler->persistFirstCommit(new Stream($this->customerStream));

        $this->chronicler->persistFirstCommit(new Stream($this->accountStream));

        try {
            $registerCustomer = RegisterCustomer::withData($this->customerId->toString(), 'steph bug');
            Publish::command($registerCustomer);

            $registerBankAccount = RegisterBankAccount::withCustomer(
                $this->accountId->toString(), $this->customerId->toString()
            );

            Publish::command($registerBankAccount);

            $numDeposit = 10;
            while (0 !== $numDeposit) {
                $makeDeposit = MakeDeposit::withCustomer(
                    $this->customerId->toString(), $this->accountId->toString(), 100
                );

                Publish::command($makeDeposit);

                --$numDeposit;
            }

            $numWithdraw = 10;
            while (0 !== $numWithdraw) {
                $makeWithdraw = MakeWithdraw::withCustomer(
                    $this->customerId->toString(), $this->accountId->toString(), 100
                );

                Publish::command($makeWithdraw);

                --$numWithdraw;
            }
        } catch (MessageDispatchFailed $exception) {
            throw $exception->getPrevious();
        }
    }
}
