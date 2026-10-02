<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Where a Design's logo lives on the bucket and how it gets there (ADR-0005).
 *
 * Every logo is stored as a square PNG of at most 512px under the Owner's own
 * directory, so a path says whose it is without a lookup. Reads and writes go
 * through the Storage API only: the bucket is S3 in production, and its paths
 * are not filesystem paths.
 */
class DesignLogo
{
    /**
     * The directory every logo lives under, and the one PruneLogos sweeps.
     */
    public const DIRECTORY = 'qr-logos';

    /**
     * Nothing is gained by keeping a source larger than the biggest hidden box
     * of the biggest PNG, and squaring a 4000x100 banner unbounded would
     * allocate a 4000x4000 canvas.
     */
    public const MAX_EDGE = 512;

    /**
     * Squares the picture and stores it for the Owner, returning its path.
     *
     * @throws \RuntimeException when the file is not a picture GD can read
     */
    public static function store(UploadedFile $file, int $userId): string
    {
        $bytes = $file->get();
        $square = is_string($bytes) ? self::square($bytes) : null;

        if ($square === null) {
            throw new \RuntimeException('That file is not a picture we can read.');
        }

        $path = self::directoryFor($userId).'/'.bin2hex(random_bytes(12)).'.png';

        Storage::put($path, $square);

        return $path;
    }

    public static function directoryFor(int $userId): string
    {
        return self::DIRECTORY.'/'.$userId;
    }

    /**
     * Whether this path is a logo the Owner uploaded themselves.
     */
    public static function belongsTo(int $userId, mixed $path): bool
    {
        return is_string($path)
            && preg_match('#^'.preg_quote(self::directoryFor($userId), '#').'/[A-Za-z0-9_-]+\.png$#', $path) === 1;
    }

    /**
     * Removes a stored logo. A file that has already gone must not block anything.
     */
    public static function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        try {
            Storage::delete($path);
        } catch (Throwable) {
            report("The logo {$path} could not be deleted.");
        }
    }

    /**
     * Pads a picture onto a transparent square canvas, scaled down to at most
     * MAX_EDGE and never up. The renderer draws a logo into a square box, so
     * squaring it here is what keeps a wide or tall logo from being cropped.
     */
    public static function square(string $bytes): ?string
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $scaledWidth = max(1, (int) round($width * $scale));
        $scaledHeight = max(1, (int) round($height * $scale));
        $side = max($scaledWidth, $scaledHeight);

        $canvas = imagecreatetruecolor($side, $side);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas,
            $source,
            intdiv($side - $scaledWidth, 2),
            intdiv($side - $scaledHeight, 2),
            0,
            0,
            $scaledWidth,
            $scaledHeight,
            $width,
            $height,
        );

        ob_start();
        imagepng($canvas);
        $square = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $square;
    }
}
