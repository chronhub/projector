<?php

declare(strict_types=1);

namespace Chronhub\Projector\Exception;

use Chronhub\Projector\Support\Contracts\Exception\ProjectorException;

class RuntimeException extends \RuntimeException implements ProjectorException
{
}
