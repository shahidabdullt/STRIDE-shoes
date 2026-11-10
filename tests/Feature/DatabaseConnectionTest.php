<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CLAUDE.md §5: MySQL is the target database and SQLite or MariaDB are not
 * equivalent substitutes. This fails loudly if the suite is pointed anywhere else.
 */
class DatabaseConnectionTest extends TestCase
{
    public function test_the_suite_runs_on_mysql_8_and_the_testing_database(): void
    {
        $connection = DB::connection();
        $version = (string) $connection->scalar('select version()');

        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertStringStartsWith('8.', $version);
        $this->assertStringNotContainsString('MariaDB', $version);
        $this->assertSame('stride_testing', $connection->getDatabaseName());
    }
}
