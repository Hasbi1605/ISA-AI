<?php

namespace App\Console\Commands;

use App\Services\DocumentLifecycleService;
use Illuminate\Console\Command;

class PurgeDeletedDocuments extends Command
{
    protected $signature = 'documents:purge-deleted {--days=7 : Permanently remove documents soft-deleted at least N days ago}';

    protected $description = 'Permanently purge soft-deleted documents after the retention window.';

    public function handle(DocumentLifecycleService $documentLifecycleService): int
    {
        $retentionDays = max((int) $this->option('days'), 0);

        $purgedCount = $documentLifecycleService->purgeSoftDeletedDocuments($retentionDays);

        $this->info("Purged {$purgedCount} document(s) soft-deleted for at least {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
