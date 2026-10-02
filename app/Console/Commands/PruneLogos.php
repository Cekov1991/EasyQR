<?php

namespace App\Console\Commands;

use App\Models\QrCode;
use App\Support\DesignLogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploaded centre logos that no QR code refers to.
 *
 * The Design editor uploads the logo before the record exists. An Owner who picks
 * a logo and then abandons the form, or replaces it, can leave the file behind
 * with nothing pointing at it, and nothing else collects it: the model's
 * `deleting` hook only reaches logos a saved record owns.
 *
 * Unreferenced is therefore not the same as abandoned. A logo on a form that is
 * still open is also unreferenced, which is what the grace period in
 * `site.orphan_logo_grace_hours` protects.
 */
class PruneLogos extends Command
{
    protected $signature = 'logos:prune {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete uploaded centre logos that no QR code refers to';

    public function handle(): int
    {
        $hours = (int) config('site.orphan_logo_grace_hours');

        if ($hours < 1) {
            $this->components->error(
                'site.orphan_logo_grace_hours must be at least 1; refusing to prune.'
            );

            return self::FAILURE;
        }

        $orphans = $this->orphans($hours);

        if ($orphans === []) {
            $this->components->info('Nothing to prune. No unreferenced logos older than '.$hours.' hours.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->warn(sprintf(
                'Dry run: %d unreferenced logos older than %d hours would be deleted.',
                count($orphans),
                $hours,
            ));

            foreach ($orphans as $path) {
                $this->line('  '.$path);
            }

            return self::SUCCESS;
        }

        Storage::delete($orphans);

        $this->components->info(sprintf(
            'Pruned %d unreferenced logos older than %d hours.',
            count($orphans),
            $hours,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function orphans(int $hours): array
    {
        $referenced = $this->referencedPaths();
        $cutoff = now()->subHours($hours)->getTimestamp();

        return array_values(array_filter(
            Storage::allFiles(DesignLogo::DIRECTORY),
            fn (string $path): bool => ! isset($referenced[$path])
                && Storage::lastModified($path) < $cutoff,
        ));
    }

    /**
     * Read out of the records rather than queried with a JSON path expression,
     * so the command behaves the same on every driver the app is run against.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $referenced = [];

        QrCode::query()
            ->select(['id', 'options'])
            ->whereNotNull('options')
            ->cursor()
            ->each(function (QrCode $qrCode) use (&$referenced): void {
                $path = $qrCode->designLogoPath();

                if ($path !== null) {
                    $referenced[$path] = true;
                }
            });

        return $referenced;
    }
}
