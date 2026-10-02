<?php

namespace App\Models;

use App\Support\QrDesignOptions;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use SimpleSoftwareIO\QrCode\Generator;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    const QR_CONTENT_TYPES = [
        'website' => '🌐 Website',
        'wifi' => '📶 Wi-Fi Network',
        'email' => '📧 Email',
        'whatsapp' => '💬 WhatsApp',
        'vcard' => '👤 Contact (vCard)',
        'sms' => '💬 SMS',
        'phone' => '📞 Phone Call',
        'calendar' => '📅 Calendar Event',
        // 'text' => '📄 Plain Text',
        // 'location' => '📍 Location',
    ];

    const QR_STYLES = [
        'round' => 'Rounded',
        'square' => 'Classic squares',
        'round_circle' => 'Circle eyes',
        'dot' => 'Dots',
    ];

    const DEFAULT_STYLE = 'round';

    /**
     * A centre logo covers modules that would otherwise carry data. Measured
     * against real decodes, 30% coverage stops scanning at error correction
     * 'M', so coverage is capped here and error correction is forced to 'H'
     * whenever a logo is present.
     */
    const LOGO_MAX_COVERAGE = 0.25;

    const LOGO_DEFAULT_COVERAGE = 0.2;

    /**
     * The overlay is never larger than a quarter of the largest permitted
     * symbol, so nothing is gained by squaring a source bigger than this — and
     * squaring a 4000x100 banner unbounded would allocate a 4000x4000 canvas.
     */
    const LOGO_MAX_SOURCE_EDGE = 512;

    /**
     * Deliberately tiny payload: fewer modules means larger ones, which is what
     * makes the difference between the styles readable at thumbnail size.
     */
    const STYLE_SAMPLE_CONTENT = 'EasyQR';

    const PROHIBITED_DOMAINS = [
        // URL shorteners (to prevent redirect chains)
        'bit.ly', 'tinyurl.com', 'short.link', 'ow.ly', 't.co', 'goo.gl',
        'tiny.cc', 'is.gd', 'buff.ly', 'rebrand.ly', 'shorturl.at',

        // Known malicious/suspicious patterns
        'free-stuff', 'click-here', 'urgent-action',

        // Add more as needed
    ];

    const PROHIBITED_KEYWORDS = [
        'phishing', 'scam', 'malware', 'virus', 'hack',
        'free-money', 'click-here-now', 'urgent-action',
        'download-now', 'claim-prize', 'congratulations-winner',
    ];

    const PROHIBITED_EXTENSIONS = [
        '.exe', '.bat', '.cmd', '.pif', '.scr', '.vbs',
    ];

    protected $fillable = [
        'name',
        'type',
        'qr_content_type',
        'qr_content_data',
        'content',
        'short_url',
        'destination_url',
        'options',
        'qr_code_image',
        'user_id',
    ];

    protected $casts = [
        'options' => 'array',
        'qr_content_data' => 'array',
        'scan_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($qrCode) {
            if (! $qrCode->short_url) {
                $qrCode->short_url = static::generateUniqueShortUrl();
            }

            if (! $qrCode->user_id) {
                $qrCode->user_id = auth()->id();
            }

            if (! $qrCode->type) {
                $qrCode->type = 'static';
            }

            if (! $qrCode->qr_content_type) {
                $qrCode->qr_content_type = 'website';
            }

            // Generate content based on QR type
            $qrCode->content = $qrCode->generateContentFromType();

            // Dynamic QR codes encode the MaystQR short URL, so every scan resolves through us
            if ($qrCode->type === 'dynamic') {
                $qrCode->content = route('qr.redirect', $qrCode->short_url);
            }

            // A code that carries a Design is drawn in the browser (ADR-0005); only
            // a code from before the Design gets a stored image.
            if (! static::hasDesign($qrCode->options ?? [])) {
                $qrCode->generateQrCode();
            }
        });

        static::updating(function ($qrCode) {
            // What a printed code encodes can never change, so a Static code only
            // lets its name and its Design move.
            if ($qrCode->type === 'static' && array_diff(array_keys($qrCode->getDirty()), ['name', 'options']) !== []) {
                throw new \Exception('Static QR codes cannot be updated after creation to preserve printed codes.');
            }

            // Dynamic QR codes: the destination may change, but never the Short URL
            // or the content the printed image encodes. Restyling is a Design change.
            if ($qrCode->type === 'dynamic' && $qrCode->isDirty(['content', 'short_url', 'qr_code_path', 'qr_code_image'])) {
                throw new \Exception('QR code image cannot be changed after creation to preserve printed codes.');
            }
        });

        static::deleting(function ($qrCode) {
            $qrCode->deleteStoredFiles();
        });

    }

    /**
     * Removes everything this record owns on the filesystem. Without this the
     * rendered image and any uploaded logo outlive the record forever.
     */
    protected function deleteStoredFiles(): void
    {
        $paths = array_filter([
            $this->qr_code_image,
            $this->options['logo_path'] ?? null,
        ], fn ($path): bool => is_string($path) && $path !== '');

        foreach ($paths as $path) {
            try {
                Storage::delete($path);
            } catch (\Throwable) {
                // A file that has already gone must not block the delete.
            }
        }
    }

    /**
     * What the code's image encodes: the Short URL redirect for a Dynamic QR
     * Code, so every scan is counted, and the formatted content for a Static one.
     */
    public function encodedContent(): string
    {
        if ($this->type === 'dynamic') {
            return route('qr.redirect', $this->short_url);
        }

        return $this->formated_content;
    }

    /**
     * The Design the code is drawn in. A code saved without one, or with
     * anything but a version 1 Design, falls back to the Rounded Look.
     *
     * @return array<string, mixed>
     */
    public function drawingDesign(): array
    {
        $design = $this->options['design'] ?? null;

        if (is_array($design) && ($design['version'] ?? null) === 1) {
            return $design;
        }

        return QrDesignOptions::defaultDesign();
    }

    public function getFormatedContentAttribute(): string
    {
        return match ($this->qr_content_type) {
            'wifi' => $this->generateWifiContent($this->qr_content_data),
            'email' => $this->generateEmailContent($this->qr_content_data),
            'whatsapp' => $this->generateWhatsAppContent($this->qr_content_data),
            'vcard' => $this->generateVCardContent($this->qr_content_data),
            'sms' => $this->generateSmsContent($this->qr_content_data),
            'phone' => $this->generatePhoneContent($this->qr_content_data),
            'text' => $this->generateTextContent($this->qr_content_data),
            'calendar' => $this->generateCalendarContent($this->qr_content_data),
            'location' => $this->generateLocationContent($this->qr_content_data),
            'website' => $this->generateWebsiteContent($this->qr_content_data),
            default => $this->destination_url ?? '',
        };
    }

    protected function generateContentFromType(): string
    {
        $data = $this->qr_content_data ?? [];

        return match ($this->qr_content_type) {
            'wifi' => $this->generateWifiContent($data),
            'email' => $this->generateEmailContent($data),
            'whatsapp' => $this->generateWhatsAppContent($data),
            'vcard' => $this->generateVCardContent($data),
            'sms' => $this->generateSmsContent($data),
            'phone' => $this->generatePhoneContent($data),
            'text' => $this->generateTextContent($data),
            'calendar' => $this->generateCalendarContent($data),
            'location' => $this->generateLocationContent($data),
            'website' => $this->generateWebsiteContent($data),
            default => $this->destination_url ?? '',
        };
    }

    protected function generateWifiContent(array $data): string
    {
        $security = $data['security'] ?? 'WPA2';
        $ssid = $data['ssid'] ?? '';
        $password = $data['password'] ?? '';
        $hidden = ($data['hidden'] ?? false) ? 'true' : 'false';

        return "WIFI:T:{$security};S:{$ssid};P:{$password};H:{$hidden};;";
    }

    protected function generateEmailContent(array $data): string
    {
        $email = $data['email'] ?? '';
        $subject = $data['subject'] ?? '';
        $body = $data['body'] ?? '';

        $params = [];
        if ($subject) {
            $params[] = 'subject='.urlencode($subject);
        }
        if ($body) {
            $params[] = 'body='.urlencode($body);
        }

        $queryString = $params ? '?'.implode('&', $params) : '';

        return "mailto:{$email}{$queryString}";
    }

    protected function generateWhatsAppContent(array $data): string
    {
        $phone = $data['phone'] ?? '';
        $message = $data['message'] ?? '';

        $queryString = $message ? '?text='.urlencode($message) : '';

        return "https://wa.me/{$phone}{$queryString}";
    }

    protected function generateVCardContent(array $data): string
    {
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $organization = $data['organization'] ?? '';
        $title = $data['title'] ?? '';
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';
        $website = $data['website'] ?? '';

        $vcard = "BEGIN:VCARD\n";
        $vcard .= "VERSION:3.0\n";
        $vcard .= "N:{$lastName};{$firstName};;;\n";
        $vcard .= "FN:{$firstName} {$lastName}\n";
        if ($organization) {
            $vcard .= "ORG:{$organization}\n";
        }
        if ($title) {
            $vcard .= "TITLE:{$title}\n";
        }
        if ($phone) {
            $vcard .= "TEL:{$phone}\n";
        }
        if ($email) {
            $vcard .= "EMAIL:{$email}\n";
        }
        if ($website) {
            $vcard .= "URL:{$website}\n";
        }
        $vcard .= 'END:VCARD';

        return $vcard;
    }

    protected function generateSmsContent(array $data): string
    {
        $phone = $data['phone'] ?? '';
        $message = $data['message'] ?? '';

        $queryString = $message ? '?body='.urlencode($message) : '';

        return "sms:{$phone}{$queryString}";
    }

    protected function generatePhoneContent(array $data): string
    {
        $phone = $data['phone'] ?? '';

        return "tel:{$phone}";
    }

    protected function generateTextContent(array $data): string
    {
        return $data['text'] ?? '';
    }

    protected function generateCalendarContent(array $data): string
    {
        $summary = $data['summary'] ?? '';
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $location = $data['location'] ?? '';
        $description = $data['description'] ?? '';

        $event = "BEGIN:VEVENT\n";
        $event .= "SUMMARY:{$summary}\n";
        if ($startDate) {
            $event .= "DTSTART:{$startDate}\n";
        }
        if ($endDate) {
            $event .= "DTEND:{$endDate}\n";
        }
        if ($location) {
            $event .= "LOCATION:{$location}\n";
        }
        if ($description) {
            $event .= "DESCRIPTION:{$description}\n";
        }
        $event .= 'END:VEVENT';

        return $event;
    }

    protected function generateLocationContent(array $data): string
    {
        $latitude = $data['latitude'] ?? '';
        $longitude = $data['longitude'] ?? '';

        return "geo:{$latitude},{$longitude}";
    }

    protected function generateWebsiteContent(array $data): string
    {
        return $data['url'] ?? $this->destination_url ?? '';
    }

    /**
     * Builds a generator configured from a stored options array, so every place
     * that renders a QR code produces the same image for the same record.
     *
     * A logo forces PNG and error correction 'H', because the library only
     * composites the overlay onto a PNG and a covered centre needs the extra
     * redundancy to stay scannable.
     *
     * @param  array{format?: string, size?: int, color?: string, errorCorrection?: string, style?: string, logo_path?: string, logo_coverage?: float}  $options
     */
    public static function buildGenerator(array $options): Generator
    {
        [$r, $g, $b] = sscanf($options['color'] ?? '#000000', '#%02x%02x%02x') ?? [0, 0, 0];

        $generator = QrCodeGenerator::format(static::effectiveFormat($options))
            ->size($options['size'] ?? 300)
            ->errorCorrection(static::effectiveErrorCorrection($options))
            ->color($r, $g, $b);

        $generator = static::applyStyle($generator, $options['style'] ?? self::DEFAULT_STYLE);

        $logo = static::logoBytes($options);

        if ($logo !== null) {
            $generator->mergeString($logo, static::logoCoverage($options));
        }

        return $generator;
    }

    /**
     * @param  array{logo_path?: string}  $options
     */
    public static function hasLogo(array $options): bool
    {
        $path = $options['logo_path'] ?? null;

        return is_string($path) && $path !== '';
    }

    /**
     * @param  array{format?: string, logo_path?: string}  $options
     */
    public static function effectiveFormat(array $options): string
    {
        return static::hasLogo($options) ? 'png' : ($options['format'] ?? 'png');
    }

    /**
     * @param  array{errorCorrection?: string, logo_path?: string}  $options
     */
    public static function effectiveErrorCorrection(array $options): string
    {
        return static::hasLogo($options) ? 'H' : ($options['errorCorrection'] ?? 'M');
    }

    /**
     * @param  array{logo_coverage?: float}  $options
     */
    protected static function logoCoverage(array $options): float
    {
        return min(
            (float) ($options['logo_coverage'] ?? self::LOGO_DEFAULT_COVERAGE),
            self::LOGO_MAX_COVERAGE,
        );
    }

    /**
     * Reads the stored logo and hands back overlay-ready bytes, or null when
     * there is nothing usable to merge.
     *
     * A logo that has gone missing, or that GD cannot decode, must degrade to a
     * plain valid QR code rather than a 500: the generator crashes on bytes it
     * cannot parse, so every failure is caught here instead.
     *
     * @param  array{logo_path?: string}  $options
     */
    protected static function logoBytes(array $options): ?string
    {
        if (! static::hasLogo($options)) {
            return null;
        }

        try {
            if (! Storage::exists($options['logo_path'])) {
                return null;
            }

            $bytes = Storage::get($options['logo_path']);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return static::squareLogo($bytes);
    }

    /**
     * Pads a logo onto a transparent square canvas.
     *
     * ImageMerge takes the overlay width from the coverage percentage and then
     * derives the height from the logo's aspect ratio, so a 100x400 logo asked
     * for at 25% becomes a full-height stripe down the middle of the symbol.
     * Squaring the source first makes that derivation a no-op, which is the
     * only way to bound the overlay in both directions.
     */
    protected static function squareLogo(string $bytes): ?string
    {
        $logo = @imagecreatefromstring($bytes);

        if ($logo === false) {
            return null;
        }

        $width = imagesx($logo);
        $height = imagesy($logo);
        $longestEdge = max($width, $height);

        $scale = min(1, self::LOGO_MAX_SOURCE_EDGE / $longestEdge);
        $scaledWidth = max(1, (int) round($width * $scale));
        $scaledHeight = max(1, (int) round($height * $scale));
        $side = max($scaledWidth, $scaledHeight);

        // Alpha blending stays off so the source's own transparency is copied
        // rather than composited against the padding.
        $canvas = imagecreatetruecolor($side, $side);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas,
            $logo,
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

        imagedestroy($logo);
        imagedestroy($canvas);

        return $square;
    }

    /**
     * Applies a module and eye shape to a generator.
     *
     * The 'round' style deliberately leaves the eye style unset so the finder
     * patterns inherit the rounded module shape. The 'dot' style must set an
     * eye style explicitly, otherwise the finder patterns render as loose dots
     * and scanners can no longer locate the symbol.
     */
    protected static function applyStyle(Generator $generator, string $style): Generator
    {
        return match ($style) {
            'square' => $generator,
            'round_circle' => $generator->style('round', 0.5)->eye('circle'),
            'dot' => $generator->style('dot', 0.85)->eye('circle'),
            default => $generator->style('round', 0.5),
        };
    }

    /**
     * Renders a small sample of a style as an inline SVG data URI, for the
     * visual style picker on the create form.
     */
    public static function styleSample(string $style): string
    {
        $key = 'qr-style-sample:'.$style.':'.substr(md5(self::STYLE_SAMPLE_CONTENT), 0, 8);

        return Cache::rememberForever($key, function () use ($style): string {
            $svg = (string) static::buildGenerator([
                'format' => 'svg',
                'size' => 160,
                'style' => $style,
            ])->generate(self::STYLE_SAMPLE_CONTENT);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        });
    }

    protected function generateQrCode(): void
    {
        $options = $this->options ?? [];

        // A logo overrides the requested format and error correction, so store
        // what was actually rendered — everything downstream reads these back.
        if (static::hasLogo($options)) {
            $options['format'] = static::effectiveFormat($options);
            $options['errorCorrection'] = static::effectiveErrorCorrection($options);

            $this->options = $options;
        }

        $format = $options['format'] ?? 'png';

        $qrCode = static::buildGenerator($options)->generate($this->content);

        // Generate unique filename
        $filename = 'qr-codes/'.uniqid().'.'.$format;

        // Store the QR code
        Storage::put($filename, $qrCode);

        // Delete old image if exists
        if ($this->qr_code_image) {
            Storage::delete($this->qr_code_image);
        }

        $this->qr_code_image = $filename;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function hasDesign(array $options): bool
    {
        return is_array($options['design'] ?? null);
    }

    /**
     * Picks a Short URL no code has, for a form to hold from the moment it opens
     * until the code is saved.
     */
    public static function reserveShortUrl(): string
    {
        return static::generateUniqueShortUrl();
    }

    protected static function generateUniqueShortUrl(int $length = 8): string
    {
        $characters = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $maxAttempts = 10;
        $attempt = 0;

        do {
            if ($attempt >= $maxAttempts) {
                $length++;
                $attempt = 0;
            }

            $shortUrl = '';
            $charactersLength = strlen($characters);

            for ($i = 0; $i < $length; $i++) {
                $shortUrl .= $characters[random_int(0, $charactersLength - 1)];
            }

            $attempt++;
        } while (static::where('short_url', $shortUrl)->exists());

        return $shortUrl;
    }

    public static function validateUrl($url): array
    {
        if (empty($url)) {
            return [
                'valid' => false,
                'message' => 'Please enter a valid URL.',
            ];
        }

        // Parse the URL
        $parsedUrl = parse_url($url);
        if (! $parsedUrl || ! isset($parsedUrl['host'])) {
            return [
                'valid' => false,
                'message' => 'Invalid URL format. Please enter a complete URL (e.g., https://example.com)',
            ];
        }

        $domain = strtolower($parsedUrl['host']);
        $fullUrl = strtolower($url);

        // Must use HTTPS for external websites (except localhost for development)
        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://localhost') && ! str_starts_with($url, 'http://127.0.0.1')) {
            return [
                'valid' => false,
                'message' => 'For security reasons, only HTTPS URLs are allowed. Please use https:// instead of http://',
            ];
        }

        // Check against prohibited domains
        foreach (self::PROHIBITED_DOMAINS as $prohibitedDomain) {
            if (str_contains($domain, strtolower($prohibitedDomain))) {
                return [
                    'valid' => false,
                    'message' => "URL shortening services like '{$prohibitedDomain}' are not allowed. Please use the direct URL to your content.",
                ];
            }
        }

        // Check URL for prohibited keywords
        foreach (self::PROHIBITED_KEYWORDS as $keyword) {
            if (str_contains($fullUrl, $keyword)) {
                return [
                    'valid' => false,
                    'message' => "URLs containing '{$keyword}' are not permitted for security reasons. Please use a different URL.",
                ];
            }
        }

        // Check for prohibited file extensions
        $path = $parsedUrl['path'] ?? '';
        foreach (self::PROHIBITED_EXTENSIONS as $extension) {
            if (str_ends_with(strtolower($path), strtolower($extension))) {
                return [
                    'valid' => false,
                    'message' => "Executable files ({$extension}) are not allowed for security reasons. Please link to a webpage instead.",
                ];
            }
        }

        // Block IP addresses (except localhost)
        if (preg_match('/^https?:\/\/\d+\.\d+\.\d+\.\d+/', $url) && ! str_starts_with($url, 'http://127.0.0.1') && ! str_starts_with($url, 'http://localhost')) {
            return [
                'valid' => false,
                'message' => 'Direct IP addresses are not allowed for security reasons. Please use a proper domain name.',
            ];
        }

        return [
            'valid' => true,
            'message' => 'URL is valid',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scans()
    {
        return $this->hasMany(QrCodeScan::class);
    }

    public function isDynamic(): bool
    {
        return $this->type === 'dynamic';
    }

    public function isStatic(): bool
    {
        return $this->type === 'static';
    }
}
