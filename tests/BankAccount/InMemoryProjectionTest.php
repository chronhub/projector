<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\BankAccount;

use Chronhub\Projector\Status;
use Chronhub\Chronicler\Stream\StreamName;
use Chronhub\Projector\Support\Facade\Project;
use Chronhub\Projector\Support\Contracts\Manager;
use Chronhub\Foundation\Aggregate\AggregateChanged;
use Chronhub\Projector\Tests\TestCaseWithOrchestra;
use Chronhub\Projector\Context\ContextualProjection;
use Chronhub\Projector\Model\InMemoryProjectionProvider;
use Chronhub\Projector\Support\Contracts\Model\ProjectionModel;
use Chronhub\Projector\Support\Contracts\Model\ProjectionProvider;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\DepositMade;
use Chronhub\Chronicler\Support\BankAccount\Model\Account\WithdrawMade;
use function json_decode;
use function iterator_count;

final class InMemoryProjectionTest extends TestCaseWithOrchestra
{
    use ProvideChronicler;

    private Manager $manager;
    private ProjectionProvider $projectionProvider;

    /**
     * @test
     */
    public function it_emit_event(): void
    {
        $this->setupProjection();

        $this->assertFalse($this->projectionProvider->projectionExists('transactions'));

        $projection = $this->manager->createProjection('transactions');

        $projection
            ->initialize(fn (): array => ['transactions' => 0, 'deposits' => 0, 'withdraws' => 0])
            ->withQueryFilter($this->manager->queryScope()->fromIncludedPosition())
            ->fromStreams($this->accountStream->toString())
            ->whenAny(function (AggregateChanged $event, array $state): array {
                /* @var ContextualProjection $this */
                if ($event instanceof DepositMade) {
                    ++$state['deposits'];
                    ++$state['transactions'];

                    $this->emit($event);
                }

                if ($event instanceof WithdrawMade) {
                    ++$state['withdraws'];
                    ++$state['transactions'];

                    $this->emit($event);
                }

                return $state;
            })->run(false);

        $this->assertTrue($this->projectionProvider->projectionExists('transactions'));

        $projectionModel = $this->projectionProvider->findByName('transactions');

        $this->assertInstanceOf(ProjectionModel::class, $projectionModel);
        $this->assertEquals('transactions', $projectionModel->name());

        $this->assertEquals([$this->accountStream->toString() => 21], json_decode($projectionModel->position(), true));

        $expectedState = ['transactions' => 20, 'deposits' => 10, 'withdraws' => 10];

        $this->assertEquals($expectedState, json_decode($projectionModel->state(), true));
        $this->assertEquals($expectedState, $projection->getState());

        $this->assertEquals(Status::IDLE, $projectionModel->status());

        $transactions = $this->chronicler->retrieveAll(new StreamName('transactions'), $this->accountId);

        $this->assertEquals(20, iterator_count($transactions));
    }

    /**
     * @test
     */
    public function it_link_event_to_a_new_stream(): void
    {
        $this->setupProjection();

        $this->assertFalse($this->projectionProvider->projectionExists('transactions'));

        $projection = $this->manager->createProjection('transactions');

        $projection
            ->initialize(fn (): array => ['transactions' => 0, 'deposits' => 0, 'withdraws' => 0])
            ->withQueryFilter($this->manager->queryScope()->fromIncludedPosition())
            ->fromStreams($this->accountStream->toString())
            ->whenAny(function (AggregateChanged $event, array $state): array {
                /* @var ContextualProjection $this */
                if ($event instanceof DepositMade) {
                    ++$state['deposits'];
                    ++$state['transactions'];
                    $this->linkTo('transactions-' . $event->aggregateId(), $event);
                }

                if ($event instanceof WithdrawMade) {
                    ++$state['withdraws'];
                    ++$state['transactions'];
                    $this->linkTo('transactions-' . $event->aggregateId(), $event);
                }

                return $state;
            })->run(false);

        $this->assertTrue($this->projectionProvider->projectionExists('transactions'));

        $projectionModel = $this->projectionProvider->findByName('transactions');

        $this->assertInstanceOf(ProjectionModel::class, $projectionModel);
        $this->assertEquals('transactions', $projectionModel->name());

        $this->assertEquals([$this->accountStream->toString() => 21], json_decode($projectionModel->position(), true));

        $expectedState = ['transactions' => 20, 'deposits' => 10, 'withdraws' => 10];

        $this->assertEquals($expectedState, json_decode($projectionModel->state(), true));
        $this->assertEquals($expectedState, $projection->getState());

        $this->assertEquals(Status::IDLE, $projectionModel->status());

        $transactions = $this->chronicler->retrieveAll(
            new StreamName('transactions-' . $this->accountId->toString()), $this->accountId
        );

        $this->assertEquals(20, iterator_count($transactions));
    }

    private function setupProjection(): void
    {
        $inMemoryProjectionProvider = $this->app->make(InMemoryProjectionProvider::class);

        $this->projectionProvider = $this->app->instance(
            InMemoryProjectionProvider::class, $inMemoryProjectionProvider);

        $this->manager = Project::create('in_memory');
    }
}
