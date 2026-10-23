<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Console;

use Givanov95\LaravelActivityLog\ActivityLogger;
use Illuminate\Console\Command;

class PruneActivityLogCommand extends Command
{
    protected $signature = 'activity-log:prune {--days= : Delete entries older than this many days (default: config activity-log.retention_days)}';

    protected $description = 'Delete activity log entries older than the retention period';

    public function handle(ActivityLogger $logger): int
    {
        $days = $this->option('days') ?? config('activity-log.retention_days');

        if ($days === null || $days === '') {
            $this->components->info('No retention period is set; nothing was pruned.');

            return self::SUCCESS;
        }

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('The retention period must be a positive number of days.');

            return self::FAILURE;
        }

        $deleted = $logger->prune((int) $days);
        $this->components->info("Deleted {$deleted} activity log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
