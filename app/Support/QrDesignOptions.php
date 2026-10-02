<?php

namespace App\Support;

use RuntimeException;

/**
 * The option sets, labels, limits and swatches a Design is built from.
 *
 * They are defined once, in public/js/qr-design-options.json beside the
 * renderer (ADR-0005), because the renderer, the browser controls and these
 * Blade controls must offer exactly the same choices. The browser gets the file
 * as the QrDesignOptions global; the Blade controls read it here and render
 * from the data, so adding a value to the file adds it to every editor.
 *
 * @phpstan-type OptionSet array{label: string, values: array<string, string>}
 * @phpstan-type Colour array{label: string, swatches: list<string>}
 */
class QrDesignOptions
{
    private const FILE = 'js/qr-design-options.json';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $loaded = null;

    /**
     * Everything in the data file.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$loaded === null) {
            $contents = file_get_contents(public_path(self::FILE));

            if ($contents === false) {
                throw new RuntimeException('The Design option data file is missing: '.self::FILE);
            }

            self::$loaded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$loaded;
    }

    /**
     * One closed set of choices (modules, corners, eye, frame, logo shape).
     *
     * @return OptionSet
     */
    public static function optionSet(string $setting): array
    {
        return self::all()['options'][$setting]
            ?? throw new RuntimeException("Unknown Design option set: {$setting}");
    }

    /**
     * The code, eye and background colours with their swatches.
     *
     * @return array<string, Colour>
     */
    public static function colours(): array
    {
        return self::all()['colours'];
    }

    /**
     * @return array{max: int, default: string}
     */
    public static function frameText(): array
    {
        return self::all()['frameText'];
    }

    /**
     * Limits, defaults and accepted files for the logo.
     *
     * @return array<string, mixed>
     */
    public static function logo(): array
    {
        return self::all()['logo'];
    }

    /**
     * The Design a new code starts with, and the one a code without a Design is
     * drawn in: the Rounded Look, no frame, no logo. Mirrors the renderer's
     * `defaultDesign()`, key for key.
     *
     * @return array<string, mixed>
     */
    public static function defaultDesign(): array
    {
        $settings = self::all()['looks']['rounded'];
        unset($settings['label']);

        return [
            'version' => 1,
            ...$settings,
            'frame' => 'none',
            'frameText' => self::frameText()['default'],
            'logo' => null,
        ];
    }

    /**
     * Replaces the data for a test, or restores the file when given null.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function use(?array $data): void
    {
        self::$loaded = $data;
    }
}
