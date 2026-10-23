<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Exceptions;

use LogicException;

final class ImmutableActivityLog extends LogicException
{
    public static function make(string $operation): self
    {
        return new self("An activity log entry cannot be {$operation}d. Use ActivityLog::scrub(), forgetScope() or activity-log:prune.");
    }
}
