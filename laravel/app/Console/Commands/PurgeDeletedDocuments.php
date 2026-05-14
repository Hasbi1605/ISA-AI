<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * This command is kept as a no-op stub for backward compatibility with
 * any scheduled task configs that reference it. Documents now use hard
 * delete (issue #159 Opsi B), so there is no soft-delete retention window
 * to purge. The command exits successfully without doing anything.
 */
class PurgeDeletedDocuments extends Command
{
    protected $signature = 'documents:purge-deleted {--days=7 : Retained for backward compatibility, no longer used}';

    protected $description = 'No-op: documents now use hard delete. Kept for backward compatibility.';

    public function handle(): int
    {
        $this->info('documents:purge-deleted is a no-op: documents now use hard delete (issue #159).');

        return self::SUCCESS;
    }
}
