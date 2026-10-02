<?php

namespace App\Console\Commands;

use App\Models\SiteEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Deletes anonymous site event counts older than the published retention window.
 *
 * This command is what makes the retention period in section 2 of the Privacy
 * Policy true. That section is rendered from config('site.event_retention_days')
 * rather than restating a number, so the document and the code cannot drift —
 * but only this command turns the number into a fact.
 *
 * The rows identify nobody, so the argument for a bounded window is not privacy
 * law but honesty and volume: we published a period, and this table grows on
 * every generated code rather than on every sale.
 *
 * Modelled closely on PruneScans, including the chunked deletes: `site_events`
 * is written on the public events endpoint, and an unbounded DELETE
 * would hold locks while the homepage queues up behind it.
 */
class PruneEvents extends Command
{
    protected $signature = 'events:prune {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete anonymous site event counts past the published retention window';

    /**
     * Rows per DELETE. See the same constant on PruneScans for why this is
     * chunked rather than a single statement.
     */
    private const CHUNK = 1000;

    public function handle(): int
    {
        $days = $this->retentionDays();

        if ($days < 1) {
            $this->components->error(
                'site.event_retention_days must be at least 1; refusing to prune.'
            );

            return self::FAILURE;
        }

        $cutoff = $this->cutoff();
        $expired = $this->expiredQuery($cutoff)->count();

        if ($expired === 0) {
            $this->components->info(sprintf(
                'Nothing to prune. No events older than %s (%d days).',
                $cutoff->toDateString(),
                $days,
            ));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->warn(sprintf(
                'Dry run: %d events older than %s (%d days) would be deleted.',
                $expired,
                $cutoff->toDateString(),
                $days,
            ));

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $batch = $this->expiredQuery($cutoff)->limit(self::CHUNK)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info(sprintf(
            'Pruned %d events older than %s (%d days).',
            $deleted,
            $cutoff->toDateString(),
            $days,
        ));

        return self::SUCCESS;
    }

    /** @return Builder<SiteEvent> */
    private function expiredQuery(Carbon $cutoff): Builder
    {
        return SiteEvent::query()->where('occurred_at', '<', $cutoff);
    }

    private function cutoff(): Carbon
    {
        return now()->subDays($this->retentionDays())->startOfDay();
    }

    private function retentionDays(): int
    {
        return (int) config('site.event_retention_days');
    }
}
