<?php

namespace App\Filament\Pages\Auth;

use App\Enums\SignupSource;
use App\Support\LandingPages;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament's registration page, plus the one thing it cannot know: which part of
 * the site sent this person here.
 *
 * The offer on the homepage links to `?ref=static-offer`. That parameter is on
 * the URL when the page is first opened and gone by the time the form submits —
 * Livewire posts to its own endpoint — so it has to be captured at mount and
 * carried on the component. Anything not named in SignupSource resolves to null
 * rather than being stored, which is what stops a public query parameter from
 * becoming a free-text column.
 *
 * The property is public, which means Livewire lets the browser set it to any
 * string it likes — so it is resolved through the enum again on the way to the
 * column, not just at mount. Validating only at mount would have left the
 * allowlist as decoration, since the value that actually gets stored is whatever
 * the component holds when the form submits.
 *
 * What remains after that is unavoidable and harmless: someone can claim an arm
 * they did not arrive through. Attribution is self-reported by construction, and
 * a visitor lying about which of our own links they followed costs us a slightly
 * wrong count. Anything worth protecting would not live behind a query parameter.
 *
 * Note that $fillable is not the protection here and cannot be: AppServiceProvider
 * calls Model::unguard(), so mass-assignment guarding is off application-wide. The
 * protection is that this column has exactly one write site, and it re-validates.
 *
 * A Landing Page link also carries `?page=`, the Signup Landing Page (ADR-0004).
 * It is handled the same way, resolved against the published pages at mount and
 * again at the write, and it is only kept beside a source that resolved: a page
 * names where a link sat, so without a link we published it describes nothing.
 * Neither value touches the session, and neither can fail a registration.
 */
class Register extends BaseRegister
{
    /**
     * The resolved arm, or null when the ref was absent, stale or invented.
     * Stored as the backing value rather than the enum so Livewire can carry it
     * across the request that submits the form.
     */
    public ?string $signupSource = null;

    /**
     * The resolved Landing Page slug, or null when there was none, it was not
     * published, or no source resolved beside it.
     */
    public ?string $signupLandingPage = null;

    /**
     * The registration link for one of our own links, on the page it sits on.
     * The homepage sends no page, since it is not a Landing Page.
     */
    public static function linkFrom(SignupSource $source, string $page = LandingPages::HOME): string
    {
        return route('filament.admin.auth.register', array_filter([
            'ref' => $source->value,
            'page' => $page === LandingPages::HOME ? null : $page,
        ]));
    }

    public function mount(): void
    {
        parent::mount();

        $ref = request()->query('ref');

        $this->signupSource = SignupSource::fromRef(is_string($ref) ? $ref : null)?->value;
        $this->signupLandingPage = $this->resolveLandingPage($this->signupSource, request()->query('page'));
    }

    /**
     * Registration, with the source and the page written in the same insert.
     *
     * This overrides a one-line parent (`create($data)`) rather than following
     * it with a second save, because `signup_source` is not fillable and never
     * should be: the whole point is that it comes from an allowlisted parameter
     * and not from whatever the form posted. `make()` still honours $fillable
     * for the form data, so the columns are set beside that rather than through it.
     */
    protected function handleRegistration(array $data): Model
    {
        $user = $this->getUserModel()::make($data);

        $user->signup_source = SignupSource::fromRef($this->signupSource);
        $user->signup_landing_page = $this->resolveLandingPage($this->signupSource, $this->signupLandingPage);

        $user->save();

        return $user;
    }

    /**
     * The page, if it is one a visitor can reach and the source beside it is
     * one we published.
     */
    private function resolveLandingPage(?string $source, mixed $page): ?string
    {
        if (SignupSource::fromRef($source) === null || ! is_string($page)) {
            return null;
        }

        return LandingPages::publishedSlug($page);
    }
}
