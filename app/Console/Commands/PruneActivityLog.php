<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * The activity log grows without bound, and the `LogsActivity` trait raises the
 * rate: every price edit, every stock adjustment, every staff change.
 *
 * A sari-sari's volume makes this a non-issue for years, so **nothing schedules
 * this** — it is deliberately manual. It exists so that retention is a decision
 * someone can act on rather than a problem discovered at a million rows.
 *
 * Deleting audit history is itself consequential, so this refuses to run
 * without a confirmed window and reports exactly what it removed.
 */
class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune
                            {--days=365 : Delete entries older than this many days}
                            {--dry-run : Report what would go without deleting it}';

    protected $description = 'Delete activity log entries older than the given number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1. Refusing to delete the whole trail.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $doomed = ActivityLog::where('created_at', '<', $cutoff);
        $count = $doomed->count();

        if ($count === 0) {
            $this->info("Nothing older than {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$count} entries older than {$cutoff->toDateString()} would be deleted.");

            return self::SUCCESS;
        }

        // Chunked rather than one statement: a large delete holds locks on a
        // table the logger writes to inside other transactions, and blocking
        // there would stall the sale being recorded.
        $deleted = 0;

        do {
            $batch = ActivityLog::where('created_at', '<', $cutoff)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} entries older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
