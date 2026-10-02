<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AgentaOsWebhookController;
use App\Http\Controllers\CrawlerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrCodeLogoController;
use App\Http\Controllers\QrCodeRedirectController;
use App\Http\Controllers\SiteEventController;
use App\Http\Controllers\SubscriptionController;
use App\Support\LandingPages;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('welcome');

/*
 * What crawlers fetch before anything else. Routes rather than files in public/
 * so that every URL inside them is built by the router and is therefore right on
 * whatever domain the app is running on. public/robots.txt was deleted when
 * these were added: the web server serves a real file in preference to a route,
 * so leaving it would have made these rules dead code.
 */
Route::get('robots.txt', [CrawlerController::class, 'robots'])->name('crawlers.robots');
Route::get('sitemap.xml', [CrawlerController::class, 'sitemap'])->name('crawlers.sitemap');
Route::get('llms.txt', [CrawlerController::class, 'llms'])->name('crawlers.llms');

/*
 * Where the page reports the things only it can see: a static code being drawn
 * (the link never leaves the browser, so there is no request to count it from),
 * a download of it, and the subscription offer being shown, dismissed or
 * clicked. See SiteEventController for why these cannot be counted server-side,
 * and LogSiteEventRequest for why it accepts nothing else.
 *
 * Inside the web group, so a valid CSRF token is required. That is not a
 * security boundary here — there is nothing to protect a visitor from — but it
 * does mean a forged count has to come from something that first loaded a page
 * of ours, which rules out the cheapest kind of noise.
 *
 * The ceiling is sized for real use. A visit sends one count for the code drawn,
 * and then at most a handful a minute (two downloads, an offer shown, an offer
 * acted on). Thirty leaves that several times over while sizing the endpoint
 * for a person rather than for a flood.
 */
Route::post('/events', [SiteEventController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('events.log');

// QR Code routes
// Scans are public and a popular code is legitimately hit by many people, so the
// ceiling is per-IP and generous. It exists to stop one client looping a URL,
// which writes a scan row every time.
Route::get('/q/{shortUrl}', [QrCodeRedirectController::class, 'redirect'])
    ->middleware('throttle:60,1')
    ->name('qr.redirect');

/**
 * The Breeze dashboard was the untouched scaffold placeholder. The product is
 * the Filament panel, so this redirects there. It keeps its name because
 * Breeze's login and registration controllers still send users to
 * route('dashboard'), and the panel gates access itself.
 */
Route::get('/dashboard', fn () => redirect()->route('filament.admin.pages.dashboard'))
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/qr-codes/{qrCode}/logo', [QrCodeLogoController::class, 'show'])->name('qr.logo');

    // Billing
    Route::post('/billing/subscribe', [SubscriptionController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('billing.subscribe');
    Route::get('/billing/success', [SubscriptionController::class, 'success'])->name('billing.success');
    Route::get('/billing/cancel', [SubscriptionController::class, 'cancel'])->name('billing.cancel');
});

// Signed by AgentaOS, not by a session. CSRF exemption lives in bootstrap/app.php.
Route::post('/webhooks/agentaos', AgentaOsWebhookController::class)->name('webhooks.agentaos');

/**
 * AgentaOS is merchant of record and reviews the site before approving it for
 * live payments, which includes checking that the price is reachable without
 * registering. It previously appeared only in clause 5 of the Terms and on the
 * billing page behind the login.
 */
Route::view('pricing', 'pricing')->name('pricing');

/*
 * Reporting a QR code that leads somewhere harmful. Public, because the people
 * who need it are strangers who scanned a poster.
 *
 * The POST is throttled: it sends mail to our own support address on an
 * unauthenticated request, so without a ceiling it is an open relay into the
 * inbox we rely on to act. Five an hour is generous for a real reporter.
 */
Route::get('report', [AbuseReportController::class, 'create'])->name('report.create');

Route::post('report', [AbuseReportController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('report.store');

/*
 * The questions people ask before they print. Public and crawlable for the same
 * reason the pricing page is: these are the queries that bring someone here in
 * the first place, and the answers carry FAQPage markup so they can be quoted
 * rather than paraphrased.
 */
Route::view('faq', 'faq')->name('faq');

Route::view('terms-and-conditions', 'terms-and-conditions');

Route::view('privacy-policy', 'privacy-policy');

Route::view('refund-policy', 'refund-policy');

/*
 * Dismisses the cookie notice. There is no matching decline route: the only
 * cookies we set are strictly necessary, which need no consent and cannot be
 * refused while still using the site. The Decline button that used to sit here
 * set a cookie and hid the banner — it suppressed nothing, because there was
 * nothing to suppress, and it implied a choice we were not honouring.
 *
 * If analytics are ever added, this becomes a real consent gate and the decline
 * route comes back with it.
 */
Route::get('cookies/accept', function () {
    return redirect()->back()->cookie('cookie_consent', 'accepted', 525600); // in minutes (1 year)
})->name('cookies.accept');

require __DIR__.'/auth.php';

/*
 * One route per published Landing Page, at the top level so the URL is the
 * search it answers. Last, so a slug can never shadow a route above it, and
 * only for published pages, so an unpublished one is a 404 rather than a
 * draft a crawler can find. See App\Support\LandingPages.
 */
LandingPages::registerRoutes();
