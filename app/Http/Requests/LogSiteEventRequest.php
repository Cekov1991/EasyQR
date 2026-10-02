<?php

namespace App\Http\Requests;

use App\Enums\TrackedEvent;
use App\Rules\SitePage;
use App\Support\LandingPages;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a browser is allowed to say happened.
 *
 * This is the narrow gate in front of the only writable-by-strangers path into
 * `site_events`, and it is deliberately narrower than the table. Three rules
 * shape it:
 *
 * 1. Only the events that genuinely cannot be seen from the server. Everything
 *    else — the checkouts — is recorded on a path we control,
 *    and TrackedEvent::isClientLoggable() is the list. Accepting more than that
 *    would let anyone POST `checkout_completed` in a loop and quietly ruin the
 *    only record we have of revenue, undetectably, because there is no
 *    identifier on these rows to spot a flood with.
 *
 * 2. The request names the event; it never composes the row. `context` is not a
 *    field here. The caller may send two allowlisted extra facts (`page` and
 *    `format`) and the request assembles the context itself, so there is no path at all for a
 *    URL, or anything else a visitor typed, to arrive in that column. This is
 *    the same promise the homepage makes about the code itself, applied to the
 *    count written alongside it.
 *
 * 3. Nothing about the sender is read. No IP, no user agent, no session. Not
 *    because they are unavailable — the request has all three — but because a
 *    row that carried any of them would make section 2 of the Privacy Policy
 *    false. EventPrivacyTest is the guard.
 *
 * The consequence, stated plainly so nobody later mistakes these numbers for
 * something they are not: client-reported counts are forgeable up to the
 * throttle. They are a signal about a funnel, never a figure to bill from.
 */
class LogSiteEventRequest extends FormRequest
{
    /**
     * The download formats the generator actually produces.
     *
     * @var array<int, string>
     */
    public const FORMATS = ['png', 'svg'];

    /**
     * Public, and it has to be: the whole point is that these events happen on
     * the homepage, where almost nobody has an account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $download = TrackedEvent::QrDownloaded->value;

        return [
            'event' => ['required', 'string', Rule::in(TrackedEvent::clientLoggableValues())],

            /*
             * Which of our pages it happened on. Optional only so that a
             * homepage script cached from before the label existed keeps
             * being counted; see context().
             */
            'page' => ['nullable', new SitePage],

            /*
             * Only meaningful for a download, and refused everywhere else, so
             * that an `offer_shown` row cannot accrete a context key that means
             * nothing on it.
             */
            'format' => [
                'nullable',
                "required_if:event,{$download}",
                "prohibited_unless:event,{$download}",
                Rule::in(self::FORMATS),
            ],

            /*
             * Refused rather than ignored, and now permanently rather than
             * pending. `variant` exists on the table for an experiment arm, and
             * the arms would have come from a holdout — showing the offer to some
             * visitors and not others. That was considered and declined: with no
             * users yet, holding it back from half of them means most early
             * visitors never see it.
             *
             * So there is exactly one arm, which is no arm at all: a column whose
             * only value is "everyone" carries no information. The experiment
             * runs through SignupSource instead, where both the offer and the
             * quiet link beside it are tagged and neither is suppressed.
             *
             * If a holdout is ever introduced, this becomes Rule::in over its arm
             * names. Until then an accepted-but-unvalidated variant would be a
             * free-text column open to the public, which is exactly the unbounded
             * cardinality TrackedEvent's second rule forbids.
             */
            'variant' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event.in' => 'That event cannot be reported by a browser.',
            'format.prohibited_unless' => 'A format only applies to a download.',
            'variant.prohibited' => 'A variant cannot be chosen by the caller.',
        ];
    }

    public function event(): TrackedEvent
    {
        return TrackedEvent::from((string) $this->validated('event'));
    }

    /**
     * The context column, assembled here from allowlisted values rather than
     * accepted wholesale from the request. A missing page means the homepage,
     * the only page a pre-label script could have come from.
     *
     * @return array<string, string>
     */
    public function context(): array
    {
        $format = $this->validated('format');

        return ['page' => LandingPages::pageLabelOrHome($this->validated('page'))]
            + ($format === null ? [] : ['format' => (string) $format]);
    }
}
