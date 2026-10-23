<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Facades;

use Givanov95\LaravelActivityLog\ActivityLogger;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Givanov95\LaravelActivityLog\PendingActivity record(string|\BackedEnum $action)
 * @method static string batchUuid()
 * @method static string newBatch()
 * @method static int scrub(\Illuminate\Database\Eloquent\Model $model, list<string> $fields = [], ?string $label = null, list<string> $properties = [])
 * @method static int forgetScope(\Illuminate\Database\Eloquent\Model $scope)
 * @method static int prune(int $days)
 *
 * @see ActivityLogger
 */
class ActivityLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogger::class;
    }
}
