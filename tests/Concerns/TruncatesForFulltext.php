<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\DatabaseTruncation;

/**
 * For tests that need MySQL FULLTEXT: InnoDB's FULLTEXT index cannot see uncommitted rows, so these tests
 * commit real rows instead of using RefreshDatabase's transaction. Laravel's DatabaseTruncation only empties
 * tables *before* a test, so this also empties them afterwards: otherwise the committed rows leak into the
 * next test class (for example a leftover AI config version makes AiConfigSeeder skip).
 */
trait TruncatesForFulltext
{
    use DatabaseTruncation;

    protected function setUpTruncatesForFulltext(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->truncateTablesForAllConnections());
    }
}
