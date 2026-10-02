# Signup attribution is a closed set

**Status:** accepted. `signup_source` exists with two arms (`static-offer`, `static-inline`). Landing Pages add a third arm and the `signup_landing_page` column, per the landing pages spec.

What we record about where an account came from is two facts on the User row, set once at registration: the **Signup Source** (which of our own links was clicked) and the **Signup Landing Page** (which Landing Page that link sat on, if any). Both are resolved against values we published — the `SignupSource` enum and the registry of published Landing Pages — and anything else is stored as null. Attribution is last click only: nothing about a visitor is held in the session before they register.

We deliberately do not store `utm_*` parameters, `gclid`, or any other value copied verbatim from the URL.

The landing pages brief asked for all of them, server-side in the session and then on the user, "to feed paid-ads conversion tracking later". We declined for three reasons:

1. **There are no paid ads.** Capturing for a consumer that does not exist yet means guessing its shape now, and the guess becomes a column we are stuck with.
2. **A free-text column is an unbounded sink.** `?ref=` and `?utm_source=` are typed by strangers. The allowlist is what keeps the column a label rather than a log of whatever anyone felt like putting in a URL — the same rule `TrackedEvent` enforces on its context.
3. **`gclid` is an advertising identifier.** Storing it against an account, and later uploading it to Google, is tracking in the sense our Privacy Policy promises to ask about first. It would also make Google a processor in `config/site.php`, which is a disclosure, not a config change.

## Consequences

- A registration we cannot attribute reads as unknown, never as an error. Attribution must never be a reason a registration does not complete.
- Two columns rather than one combined ref (`lp-<slug>`) keep the offer-versus-inline experiment intact on every page, and keep the enum from growing a case per page.
- Signups that wander off a Landing Page before registering are uncredited. If that turns out to be most of them, first-touch capture in the session is a small, additive change.
- When paid ads are real, `gclid` comes back as its own decision, shipped together with the Privacy Policy change, the processor entry and whatever consent it needs. Clicks from before that day are lost, and that is accepted.
