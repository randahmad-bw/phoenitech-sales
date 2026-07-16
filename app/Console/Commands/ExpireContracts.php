<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;

/**
 * Automatically marks active contracts as completed when their end_date has passed.
 */
class ExpireContracts extends Command
{
    protected $signature   = 'contracts:expire';
    protected $description = 'Mark active contracts whose end_date has passed as completed.';

    public function handle(): int
    {
        $updated = Contract::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'completed', 'progress_percentage' => 100]);

        $this->info("Expired {$updated} contract(s) → marked as completed.");

        return self::SUCCESS;
    }
}
