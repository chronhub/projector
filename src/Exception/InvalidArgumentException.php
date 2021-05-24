<?php

declare(strict_types=1);

namespace Chronhub\Projector\Exception;

use Chronhub\Projector\Support\Contracts\Exception\ProjectorException;

final class InvalidArgumentException extends \InvalidArgumentException implements ProjectorException
{
}
