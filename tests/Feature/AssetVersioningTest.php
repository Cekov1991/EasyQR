<?php

namespace Tests\Feature;

use App\Support\Asset;
use Tests\TestCase;

/**
 * Cache-busting for the hand-written stylesheet.
 *
 * public/css/site.css is served off disk with no version in its URL, so a style
 * change reached nobody who had already visited the site. The failure is silent
 * — the deploy succeeds and the file on disk is right — which is why it needs a
 * test rather than a note.
 */
class AssetVersioningTest extends TestCase
{
    private string $temporaryFile = 'css/asset-versioning-test.css';

    protected function setUp(): void
    {
        parent::setUp();

        Asset::forgetVersions();
    }

    protected function tearDown(): void
    {
        if (is_file(public_path($this->temporaryFile))) {
            unlink(public_path($this->temporaryFile));
        }

        parent::tearDown();
    }

    private function writeTemporaryFile(string $contents): void
    {
        file_put_contents(public_path($this->temporaryFile), $contents);
        Asset::forgetVersions();
    }

    public function test_the_vendored_qr_scripts_are_versioned_like_the_stylesheet(): void
    {
        foreach (['js/qrcode-generator.js', 'js/qr-renderer.js'] as $script) {
            $this->assertFileExists(public_path($script));
            $this->assertMatchesRegularExpression('/\?v=[0-9a-f]{8}$/', Asset::versioned($script));
        }
    }

    public function test_a_real_file_gets_a_version_appended(): void
    {
        $this->assertMatchesRegularExpression(
            '/\/css\/site\.css\?v=[0-9a-f]{8}$/',
            Asset::versioned('css/site.css'),
        );
    }

    /**
     * The whole point: different bytes must produce a different URL, or the
     * browser has no way to know it is holding something stale.
     */
    public function test_changing_the_file_changes_the_url(): void
    {
        $this->writeTemporaryFile('.a { color: red }');
        $before = Asset::versioned($this->temporaryFile);

        $this->writeTemporaryFile('.a { color: blue }');
        $after = Asset::versioned($this->temporaryFile);

        $this->assertNotSame($before, $after);
    }

    /**
     * A content hash, not a modification time. Two servers that checked the code
     * out at different moments must advertise the same URL for identical bytes,
     * or one cached file becomes two and the version stops meaning anything.
     */
    public function test_identical_content_produces_an_identical_url(): void
    {
        $this->writeTemporaryFile('.a { color: red }');
        $first = Asset::versioned($this->temporaryFile);

        touch(public_path($this->temporaryFile), time() + 3600);
        $this->writeTemporaryFile('.a { color: red }');
        $second = Asset::versioned($this->temporaryFile);

        $this->assertSame($first, $second);
    }

    /**
     * A renamed or missing file must not take every page down with it. The
     * stylesheet going missing is already visible; a 500 everywhere is worse.
     */
    public function test_a_missing_file_is_returned_unversioned(): void
    {
        $url = Asset::versioned('css/does-not-exist-'.uniqid().'.css');

        $this->assertStringNotContainsString('?v=', $url);
    }

    /**
     * The reason any of this exists: the rendered page must carry the version, not
     * merely be able to compute one.
     */
    public function test_the_public_layout_serves_a_versioned_stylesheet(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/css/site.css?v=', false);
    }
}
