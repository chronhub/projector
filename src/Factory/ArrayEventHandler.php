<?php

declare(strict_types=1);

namespace Chronhub\Projector\Factory;

use Chronhub\Projector\Context\Context;
use Chronhub\Foundation\Message\DomainEvent;
use Chronhub\Foundation\Support\Facade\AliasMessage;
use Chronhub\Projector\Support\Contracts\Repository;

final class ArrayEventHandler extends EventHandlers
{
    public function __construct(private array $eventHandlers)
    {
    }

    public function __invoke(Context $context, DomainEvent $event, int $key, ?Repository $repository): bool
    {
        if ( ! $this->preProcess($context, $event, $key, $repository)) {
            return false;
        }

        if ( ! $eventHandler = $this->determineEventHandler($event)) {
            if ($repository) {
                $this->persistOnReachedCounter($context, $repository);
            }

            return ! $context->runner()->isStopped();
        }

        $state = $eventHandler($event, $context->state()->getState());

        return $this->afterProcess($context, $state, $repository);
    }

    private function determineEventHandler(DomainEvent $event): ?callable
    {
        $eventAlias = AliasMessage::instanceToAlias($event);

        return $this->eventHandlers[$eventAlias] ?? null;
    }
}
