<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeDeletedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_command_is_noop_after_hard_delete_migration(): void
    {
        // documents:purge-deleted is now a no-op because documents use hard delete (issue #159).
        $this->artisan('documents:purge-deleted', ['--days' => 7])
            ->expectsOutput('documents:purge-deleted is a no-op: documents now use hard delete (issue #159).')
            ->assertExitCode(0);
    }

    public function test_deleted_documents_purge_is_registered_in_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('documents:purge-deleted --days=7')
            ->assertExitCode(0);
    }
}
