<?php

declare(strict_types=1);

namespace Givanov95\LaravelActivityLog\Tests\Feature;

use Givanov95\LaravelActivityLog\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class MigrationTest extends TestCase
{
    /**
     * MySQL and MariaDB refuse identifiers over 64 characters; sqlite, which the tests run on, does
     * not, so the length is checked here.
     */
    public function test_index_names_fit_mysql(): void
    {
        foreach ([config('activity-log.table'), config('activity-log.relations_table')] as $table) {
            $this->assertNotEmpty(Schema::getIndexes($table));

            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), "Index name [{$index['name']}] is too long for MySQL.");
            }
        }
    }
}
