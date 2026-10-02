<?php

namespace Tests\Feature;

use App\Models\QrCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The homepage judges a link in the browser (public/js/qr-link.js) with the
 * same rules the server applies to a dynamic code's destination. This is the
 * server half of the pin: tests/js/link.test.mjs runs the same fixture through
 * the browser half, so neither can drift from the other without a test failing.
 */
class LinkValidationParityTest extends TestCase
{
    /**
     * @return array{rules: array<string, array<int, string>>, cases: array<int, array{url: string, valid: bool, message: string|null}>}
     */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../fixtures/link-validation.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string|null}>
     */
    public static function caseProvider(): array
    {
        $cases = [];

        foreach (self::fixture()['cases'] as $case) {
            $cases[$case['url']] = [$case['url'], $case['valid'], $case['message']];
        }

        return $cases;
    }

    #[DataProvider('caseProvider')]
    public function test_the_server_agrees_with_the_fixture_the_browser_is_held_to(string $url, bool $valid, ?string $message): void
    {
        $result = QrCode::validateUrl($url);

        $this->assertSame($valid, $result['valid']);

        if (! $valid) {
            $this->assertSame($message, $result['message']);
        }
    }

    public function test_the_browser_is_given_the_servers_prohibited_lists(): void
    {
        $this->assertSame([
            'domains' => QrCode::PROHIBITED_DOMAINS,
            'keywords' => QrCode::PROHIBITED_KEYWORDS,
            'extensions' => QrCode::PROHIBITED_EXTENSIONS,
        ], self::fixture()['rules']);
    }

    public function test_the_page_hands_those_lists_to_the_browser(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        foreach (QrCode::PROHIBITED_DOMAINS as $domain) {
            $this->assertStringContainsString($domain, $page);
        }

        $this->assertStringContainsString('tinyurl.com', $page);
        $this->assertStringContainsString('download-now', $page);
        $this->assertStringContainsString('.exe', $page);
    }
}
