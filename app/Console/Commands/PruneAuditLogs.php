<?php

namespace App\Console\Commands;

use App\Application\Services\AuditLogService;
use Illuminate\Console\Command;

/**
 * Thins the audit trail by deleting entries older than the retention window.
 *
 * This is the only operation in the app that removes audit rows, which is why
 * it is an explicit command rather than something a request can trigger.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days= : Keep entries newer than this many days (defaults to config audit.retention_days)}';

    protected $description = 'Delete audit log entries older than the retention window.';

    public function handle(AuditLogService $service): int
    {
        $days = (int) ($this->option('days') ?? config('audit.retention_days', 365));

        if ($days <= 0) {
            $this->info('Audit retention is unlimited (0 days) — nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = $service->prune($days);

        $this->info("Pruned {$deleted} audit log entr(ies) older than {$days} days.");

        return self::SUCCESS;
    }
}
