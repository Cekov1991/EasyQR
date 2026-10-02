<?php

namespace App\Support;

/**
 * The server's check of a Design submitted through a form (ADR-0005). It is the
 * only server module that knows a Design's shape: the closed sets, the hex
 * colours, the size and padding ranges, the frame text length, the version and
 * the scan limits. The browser enforces the same limits, but the server cannot
 * look at an image to know a code is readable, so it re-checks every save.
 *
 * Contrast is the WCAG relative-luminance formula with the thresholds of
 * public/js/qr-renderer.js, so the two cannot disagree.
 */
class QrDesignValidator
{
    private const VERSION = 1;

    private const BLOCK_CONTRAST = 3;

    private const SETTINGS = [
        'version', 'dot', 'corner', 'eye', 'codeColor', 'eyeColor', 'bgColor', 'frame', 'frameText', 'logo',
    ];

    private const LOGO_SETTINGS = ['path', 'shape', 'size', 'padding', 'backing', 'clearSpace'];

    private const CHOICES = [
        'dot' => 'module shape',
        'corner' => 'corner',
        'eye' => 'eye',
        'frame' => 'frame',
    ];

    private const COLOURS = [
        'codeColor' => 'code colour',
        'eyeColor' => 'eye colour',
        'bgColor' => 'background colour',
    ];

    /**
     * Every reason this Design cannot be saved, each a sentence fit to show to the Owner.
     *
     * @return list<string>
     */
    public static function errors(mixed $design): array
    {
        if (! is_array($design) || ($design !== [] && array_is_list($design))) {
            return ['The Design must be a set of settings.'];
        }

        $errors = [];

        foreach (array_diff(array_keys($design), self::SETTINGS) as $unknown) {
            $errors[] = 'The Design has a setting that does not exist: '.$unknown.'.';
        }

        if (($design['version'] ?? null) !== self::VERSION) {
            $errors[] = 'The Design version must be '.self::VERSION.'.';
        }

        foreach (self::CHOICES as $setting => $name) {
            $allowed = array_keys(QrDesignOptions::optionSet($setting)['values']);

            if (! in_array($design[$setting] ?? null, $allowed, true)) {
                $errors[] = 'Choose a '.$name.' from the list: '.implode(', ', $allowed).'.';
            }
        }

        foreach (self::COLOURS as $setting => $name) {
            if (! self::isHex($design[$setting] ?? null)) {
                $errors[] = 'The '.$name.' must be a six-digit hex colour like #348fad.';
            }
        }

        $errors = [...$errors, ...self::frameTextErrors($design['frameText'] ?? null)];

        if (! array_key_exists('logo', $design)) {
            $errors[] = 'The Design must say whether it has a logo.';
        } elseif ($design['logo'] !== null) {
            $errors = [...$errors, ...self::logoErrors($design['logo'])];
        }

        return [...$errors, ...self::scanErrors($design)];
    }

    /**
     * The width of the box the logo hides, as a percent of the code's width: the
     * logo plus its padding on each side when a white backing or clear space
     * covers that padding, otherwise the logo alone.
     *
     * @param  array{size: int|float, padding: int|float, backing: bool, clearSpace: bool}|null  $logo
     */
    public static function logoCoverage(?array $logo): float
    {
        if ($logo === null) {
            return 0;
        }

        return $logo['backing'] || $logo['clearSpace']
            ? $logo['size'] + 2 * $logo['padding']
            : $logo['size'];
    }

    /**
     * WCAG contrast ratio between two six-digit hex colours.
     */
    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(
            function (int $offset) use ($hex): float {
                $channel = hexdec(substr(ltrim($hex, '#'), $offset, 2)) / 255;

                return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            [0, 2, 4],
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private static function frameTextErrors(mixed $text): array
    {
        $max = QrDesignOptions::frameText()['max'];

        if (! is_string($text)) {
            return ['Frame text must be text.'];
        }

        if (mb_strlen($text) > $max) {
            return ['Frame text can be at most '.$max.' characters.'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function logoErrors(mixed $logo): array
    {
        if (! is_array($logo)) {
            return ['The logo must be a set of settings.'];
        }

        $errors = [];
        $limits = QrDesignOptions::logo();

        foreach (array_diff(array_keys($logo), self::LOGO_SETTINGS) as $unknown) {
            $errors[] = 'The logo has a setting that does not exist: '.$unknown.'.';
        }

        if (! is_string($logo['path'] ?? null) || $logo['path'] === '') {
            $errors[] = 'The logo needs a logo file.';
        }

        $shapes = array_keys(QrDesignOptions::optionSet('logoShape')['values']);

        if (! in_array($logo['shape'] ?? null, $shapes, true)) {
            $errors[] = 'Choose a logo shape from the list: '.implode(', ', $shapes).'.';
        }

        if (! self::isNumberBetween($logo['size'] ?? null, $limits['size']['min'], $limits['size']['max'])) {
            $errors[] = 'The logo size must be between '.$limits['size']['min'].'% and '.$limits['size']['max'].'%.';
        }

        $padding = $logo['padding'] ?? null;

        if (! self::isNumberBetween($padding, $limits['padding']['min'], $limits['padding']['max']) || ! self::isHalfStep($padding)) {
            $errors[] = 'The logo padding must be between '.$limits['padding']['min'].'% and '.$limits['padding']['max'].'%, in steps of '.$limits['padding']['step'].'.';
        }

        foreach (['backing', 'clearSpace'] as $switch) {
            if (! is_bool($logo[$switch] ?? null)) {
                $errors[] = 'The logo '.$switch.' must be on or off.';
            }
        }

        return $errors;
    }

    /**
     * The limits that make a code unreadable, asked of whatever part of the
     * Design is well formed enough to measure.
     *
     * @param  array<string, mixed>  $design
     * @return list<string>
     */
    private static function scanErrors(array $design): array
    {
        $errors = [];

        if (self::isHex($design['codeColor'] ?? null) && self::isHex($design['bgColor'] ?? null)) {
            $contrast = self::contrast($design['codeColor'], $design['bgColor']);

            if ($contrast < self::BLOCK_CONTRAST) {
                $errors[] = 'The code and background are too close in colour ('.round($contrast, 1).':1). Scanners need at least '.self::BLOCK_CONTRAST.':1.';
            }
        }

        $logo = $design['logo'] ?? null;

        if (is_array($logo) && self::logoErrors($logo) === []) {
            $coverage = self::logoCoverage($logo);
            $hiddenBox = QrDesignOptions::logo()['hiddenBox'];

            if ($coverage > $hiddenBox) {
                $errors[] = sprintf("The logo and its padding hide %s%% of the code's width. Keep it to %s%% or less.", round($coverage, 1), $hiddenBox);
            }
        }

        return $errors;
    }

    private static function isNumberBetween(mixed $value, int|float $min, int|float $max): bool
    {
        return (is_int($value) || is_float($value)) && $value >= $min && $value <= $max;
    }

    private static function isHalfStep(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && abs($value * 2 - round($value * 2)) < 1e-9);
    }
}
