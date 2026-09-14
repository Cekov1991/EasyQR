# Two Plans — Implementation Plan

Introduce a **monthly** plan at **$5.90** and raise the **yearly** plan from **$27
to $49**. Nothing is launched, there are no live subscriptions, and no data needs
migrating.

**Status:** planned, nothing implemented.
**Related:** [subscription MVP plan](./subscription-implementation-plan.md) ·
[AgentaOS go-live](./agentaos-launch/README.md) ·
[ADR-0001](./adr/0001-account-level-entitlement-replaces-per-code-expiry.md) ·
[ADR-0002](./adr/0002-entitlement-is-a-local-date-reconciled-from-agentaos.md)

---

## The blocker that shapes everything

Selling a monthly plan on today's code **gives it away for a year**.

`GrantSubscriptionEntitlement:77` grants a provisional entitlement of one billing
interval from now, and it reads that interval from **config**, not from the
subscription being paid for:

```php
$user->grantEntitlementThrough(BillingInterval::configured()->endFrom(now()));
```

`ResolveAgentaOsSubscription` is supposed to replace that provisional date with
the real `currentPeriodEnd`. It cannot. `User::grantEntitlementThrough()`
(`app/Models/User.php:100-112`) refuses to shorten an existing date — deliberately,
per ADR-0002, so that a stale reply can never darken a paying customer's printed
codes:

```php
if ($this->entitled_until !== null && $this->entitled_until->greaterThanOrEqualTo($candidate)) {
    return false;
}
```

So: monthly buyer pays $5.90 → provisional grant of `now + 1 year + 7 days grace`
→ follow-up job computes `now + 1 month + 7 days` → **rejected as a shortening** →
the year stands. The daily `billing:sync` uses the same method and is equally
powerless. Nothing in the system ever corrects it.

`tests/Feature/Subscription/WebhookTest.php:259` already carries the assertion
*"A monthly interval must not grant a year of entitlement"* — it passes today only
because config and reality happen to agree at one plan.

**Consequence:** the subscription row must know which plan bought it, and the
grant must read the row. That is Phases 1 and 3, and no monthly checkout may open
before both are done. Phase 4's UI is cosmetic by comparison.

## Decisions

| Area | Decision |
|---|---|
| Yearly price | **$49** (from $27) |
| Monthly price | **$5.90** — $70.80/year, so yearly saves ~31% |
| Default / highlighted plan | **Yearly.** Monthly is the secondary option on both the pricing page and the billing page. |
| Plan switching | **None.** Cancel and resubscribe. No proration, no upgrade path. Nobody is on a plan yet, and AgentaOS's cancel-at-period-end model is already wired. |
| Refund window | **14 days on a first payment per plan**, not on every renewal. See [Phase 5](#phase-5--legal-and-email-copy). |
| Where the interval lives | **The `Plan` enum**, by `match`. Not config. A plan named `monthly` billed yearly is not a state worth being able to express. |
| Where the price lives | **Config**, env-overridable per plan, so a price change is not a deploy. |
| Where the link ids live | **`config/services.php`**, beside the API key. They are provider credentials. |
| Old `subscription.price` | **Deleted**, not aliased. A stale reader must break loudly, not quietly charge $27. |
| Trial | Unchanged: 7 days, no card, account-level. Same trial regardless of which plan is bought afterwards. |
| Grace | Unchanged at 7 days for both plans — see [Open questions](#open-questions). |

## Pricing arithmetic, for the copy

| | Monthly | Yearly |
|---|---|---|
| Charged | $5.90/month | $49/year |
| Per year | $70.80 | $49.00 |
| Effective monthly | $5.90 | $4.08 |
| Yearly saves | — | $21.80, ~31%, "over 8 months for the price of 12" |

Both are tax **inclusive** — AgentaOS is merchant of record and carves destination
VAT out of these amounts rather than adding it on top. A German buyer at 19% leaves
roughly $41.18 gross on the yearly before AgentaOS's own fee.

---

## Phase 1 — A plan becomes a thing

**Goal:** the codebase can name two plans and knows what each costs and how often
it bills. No UI change; everything still defaults to yearly, now at $49.

### New — `app/Enums/Plan.php`

```php
enum Plan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public static function default(): self;      // config('subscription.default_plan')
    public function interval(): BillingInterval; // match — Monthly => Month, Yearly => Year
    public function price(): float;              // config("subscription.plans.{$this->value}.price")
    public function paymentLinkId(): ?string;    // config("services.agentaos.payment_links.{$this->value}")
    public function label(): string;             // "Monthly" / "Yearly"
}
```

`interval()` is a `match`, not a config read. That is the whole point: the two
facts that drifted apart in `18f75cb` — the period billed and the period granted —
now cannot be configured into disagreement.

### Changed — `app/Enums/BillingInterval.php`

Delete `configured()`. Delete the class docblock paragraph beginning *"Only Year is
in use…"* — both cases are now in use, and the comment's warning that "changing the
interval is a product decision beyond this enum" is exactly the decision this plan
carries out. `endFrom()` is unchanged and remains the arithmetic everything relies on.

### Changed — `config/subscription.php:33-46`

Replace the `price` / `currency` / `billing_interval` block with:

```php
'default_plan' => env('SUBSCRIPTION_DEFAULT_PLAN', 'yearly'),

'plans' => [
    'monthly' => ['price' => (float) env('SUBSCRIPTION_MONTHLY_PRICE', 5.90)],
    'yearly'  => ['price' => (float) env('SUBSCRIPTION_YEARLY_PRICE', 49)],
],

'currency' => env('SUBSCRIPTION_CURRENCY', 'USD'),
```

`billing_interval` is gone. `price` is gone. Keep the merchant-of-record comment —
it is as true of two prices as of one.

### Changed — `app/Support/SubscriptionPrice.php`

`formatted()` and `perInterval()` take a required `Plan`:

```php
public static function formatted(Plan $plan): string;    // "$49" / "$5.90"
public static function perInterval(Plan $plan): string;  // "$49/year" / "$5.90/month"
public static function currency(): string;               // unchanged
```

**Required, not defaulted.** A default would let every existing call site keep
compiling while silently quoting the yearly price in copy that now needs both —
which is the failure this class was written to prevent. Ten call sites should each
be forced to state which plan they mean.

The existing `str_ends_with($amount, '.00')` logic finally earns itself: $5.90
renders as **`$5.90`**, not `$5.9`. The 2026-08 note in
[phase-01](./agentaos-launch/phase-01-public-pricing.md#L158) predicted exactly
this and `test_real_cents_are_kept` already pins it.

Add one method for the "from" copy on pages that name a single figure:

```php
public static function cheapestPerInterval(): string;  // "$5.90/month"
```

### Migration — `add_plan_to_subscriptions_table`

```php
$table->string('plan')->nullable()->after('user_id');
```

Nullable, and no backfill — the table is empty. Nullable rather than
`default('yearly')` because a row without a plan is a genuine anomaly (a payment
we did not initiate) and should be visible as one rather than dressed up as a
yearly sale.

### Changed — `app/Models/Subscription.php`

Cast it, so the rest of the code handles a `Plan`, never a string:

```php
'plan' => Plan::class,
```

Do **not** add a `plan()` method beside the `plan` attribute — Eloquent would read
it as a relationship. Where a guaranteed value is needed, add:

```php
public function planOrDefault(): Plan
{
    return $this->plan ?? Plan::default();
}
```

### Changed — `app/Support/StructuredData.php:154-157`

`billingUnitCode()` takes a `Plan` instead of calling `BillingInterval::configured()`.
The `match` over MON / ANN is otherwise unchanged.

### Tests

| File | Change |
|---|---|
| `tests/Unit/PlanTest.php` | **New.** Each plan maps to its interval; `price()` reads config; an unknown `default_plan` throws rather than assuming yearly. |
| `tests/Unit/BillingIntervalTest.php` | Delete the two `configured()` tests; keep `endFrom` and the non-mutation test. The "unsupported interval throws" case moves to `PlanTest` as "an unsupported default plan throws". |
| `tests/Unit/SubscriptionPriceTest.php` | Rewrite around the `Plan` argument. Add `test_a_price_with_real_cents_keeps_them` against $5.90 specifically — this is now a shipped price, not a hypothetical. |

**Optional:** this is the second time the "which period" question has caused a
defect (first `18f75cb`, now the grant/shorten interaction above). An
`docs/adr/0003-the-plan-is-a-property-of-the-subscription.md` would be cheap and
would stop the next person reintroducing a global interval.

---

## Phase 2 — Two payment links at AgentaOS

**Goal:** each plan has its own link, created reproducibly, with the amount and
interval baked in at AgentaOS's end.

### The trap this phase closes

The price the buyer is **charged** lives on the AgentaOS payment link, not in our
config. `AgentaOsClient::createSubscriptionPaymentLink()` sends
`amount` and `billingInterval` once, at creation. Changing `SUBSCRIPTION_PRICE`
afterwards changes every price the site *displays* while checkout still charges
the old one — a pricing misstatement with a merchant of record watching. Raising
yearly to $49 therefore **requires recreating the link**, in test and again in live.

Nothing in the code enforces the pairing, and this plan does not try to: enforcing
it means storing the link's amount locally and reconciling it, which is a bigger
change than the launch needs. Phase 6's checklist carries it instead.

### Changed — `config/services.php:38-43`

```php
'payment_links' => [
    'monthly' => env('AGENTAOS_MONTHLY_PAYMENT_LINK_ID'),
    'yearly'  => env('AGENTAOS_YEARLY_PAYMENT_LINK_ID'),
],
```

`payment_link_id` is removed. `AGENTAOS_PAYMENT_LINK_ID` in every `.env` becomes
`AGENTAOS_YEARLY_PAYMENT_LINK_ID` — and its value must be replaced anyway, since
the old link charges $27.

### Changed — `app/Services/AgentaOS/AgentaOsClient.php:38-48`

`createSubscriptionPaymentLink(Plan $plan, string $name, string $description)` —
amount and `billingInterval` come from the plan.

### Changed — `app/Console/Commands/CreateAgentaOsPaymentLink.php`

- Signature gains `{plan : monthly or yearly}`; invalid values fail before any call.
- Default name becomes `config('app.name').' '.$plan->label()`.
- `$description` is unchanged (quota copy, identical for both plans).
- The confirmation line and the `.env` line it prints name the plan-specific
  variable, so a copy-paste cannot put the monthly id in the yearly slot.
- `protected $description` drops the word "yearly".

### Changed — `.env.example:98-108`

Replace `AGENTAOS_PAYMENT_LINK_ID` with the two new keys, and `SUBSCRIPTION_PRICE`
with `SUBSCRIPTION_MONTHLY_PRICE` / `SUBSCRIPTION_YEARLY_PRICE` /
`SUBSCRIPTION_DEFAULT_PLAN`. Update the comment: the command now runs **twice per
environment**, once per plan.

### Tests

`tests/Feature/Subscription/CheckoutTest.php` `setUp()` already sets
`services.agentaos.payment_link_id`; it moves to the array form. No new tests here —
the command is exercised in Phase 6 against the sandbox, and faking it proves
nothing the client tests do not already cover.

---

## Phase 3 — Checkout carries the plan (the correctness phase)

**Goal:** the plan a buyer chose reaches the AgentaOS link, the subscription row,
and the entitlement grant, without passing through config.

### New — `app/Http/Requests/StoreSubscriptionCheckoutRequest.php`

Follows `StoreAbuseReportRequest`. One rule:

```php
'plan' => ['required', Rule::enum(Plan::class)],
```

`required`, not `sometimes` with a default — a checkout that does not say what it
is buying is a bug, and it should 422 rather than pick a plan on the buyer's behalf.

### Changed — `app/Http/Controllers/SubscriptionController.php:20-66`

- Take the request, resolve `Plan`, read `$plan->paymentLinkId()`.
- The `blank($linkId)` guard and its `payment-link-missing` alert stay, and the
  alert message and context gain the plan — "nobody can subscribe" is now
  "nobody can subscribe *monthly*", and an operator needs to know which.
- The `checkout-creation-failed` alert context gains `plan` for the same reason.
- The `Subscription::updateOrCreate` call writes `'plan' => $plan`.

### Changed — `app/Jobs/GrantSubscriptionEntitlement.php:77`

```php
$user->grantEntitlementThrough($subscription->planOrDefault()->interval()->endFrom(now()));
```

Read after the `fill()->save()`, so it uses the row's plan. This is the line the
whole plan exists for.

### Changed — `routes/web.php:52-54`

Unchanged in shape. The `throttle:10,1` stays.

### Not changed

`ResolveAgentaOsSubscription` and `SyncAgentaOsSubscriptions` need nothing. They
read `currentPeriodEnd` from AgentaOS, which is already correct per plan. They do
not learn the plan and do not need to — with Phase 1 and 3 in place the provisional
date is already right, so there is no shortening for `grantEntitlementThrough()`
to refuse.

### Tests

| File | Change |
|---|---|
| `CheckoutTest.php` | Existing posts gain `['plan' => 'yearly']`. **New:** a monthly checkout sends the monthly `linkId`; a checkout with no plan is rejected 422; a checkout with `plan=fortnightly` is rejected; the created row stores the plan. |
| `WebhookTest.php:259` | The existing "a monthly interval must not grant a year" test is rewritten to drive it the real way — open a monthly checkout, fire the webhook, assert `entitled_until` is ~1 month + grace, not ~1 year. It is the regression test for the blocker at the top of this document and should read like one. |
| **New** `WebhookTest` case | A webhook for a session with **no** local row falls back to the default plan and still grants, rather than throwing. |

---

## Phase 4 — The customer can choose

**Goal:** both plans are visible, comparable and buyable. Nothing here is
load-bearing for correctness; all of it is load-bearing for the AgentaOS review,
which fails a site whose pricing is not clear before purchase.

### `resources/views/filament/pages/billing.blade.php:70-95`

The Subscribe section becomes two options rather than one button. Each posts to
`billing.subscribe` with a hidden `plan`. Yearly first and primary, monthly
secondary, with the saving stated on the yearly.

Line 34 also hardcodes `'yearly'` as the fallback renewal cadence when
`current_period_end` is null — it becomes the subscription's own plan noun.

### `app/Filament/Pages/Billing.php:32-35`

`getFormattedPrice()` becomes `getPriceFor(Plan $plan)`. While here, note the
cancel modal copy ("You keep full access until the end of the period you have
already paid for") is already plan-neutral and needs nothing.

### `app/Filament/Widgets/SubscriptionBanner.php:47-50`

Drop the inline `rtrim(rtrim(number_format(...)))` — it is the last surviving copy
of the expression `SubscriptionPrice` was created to eliminate, and it renders
$5.90 as `$5.9`. The banner links to the billing page where the choice is made, so
its label becomes "Subscribe from `SubscriptionPrice::cheapestPerInterval()`".

### `resources/views/pricing.blade.php`

- `@section('description')` (line 4) — both prices.
- Hero lead (line 16) — "from $5.90 a month, or $49 a year".
- The Dynamic QR card (lines 47-52) — two price lines. `.eq-price` is a flex row
  with a baseline-aligned `.eq-price-period`, so a second `.eq-price` block below
  the first needs no new CSS; only a small `.eq-price--alt` for the secondary size.
- "What you pay" (lines 78-83) — both prices, both cadences, the saving, and that
  either renews automatically until cancelled.

### `resources/views/home.blade.php:52-57`

The teaser card names one figure. Use the monthly with a "from", and let the
pricing page carry the comparison — a homepage card that tries to show two prices
buys confusion for no conversion.

### `app/Support/PublicPages.php:37`

The pricing summary is written for a machine that will paraphrase it, so it should
state both prices explicitly rather than "from": *"Dynamic QR codes cost $5.90 a
month or $49 a year, tax included, after a 7-day free trial that needs no payment
details."*

### `resources/views/crawlers/llms.blade.php:17`

Same, in the same words.

### `app/Support/StructuredData.php:107-137`

`paidOffer()` becomes `paidOffer(Plan $plan)` and `forProduct()` emits **three**
offers: free, monthly, yearly. Each paid offer keeps `valueAddedTaxIncluded => true`
and gets its own `unitCode` (`MON` / `ANN`) and `name` ("Dynamic QR codes, monthly").

### Tests

| File | Change |
|---|---|
| `PublicPagesTest.php:82,109,114` | `assertSee('per year')` becomes assertions for both cadences. Line 114's `config(['subscription.price' => 42])` moves to the per-plan key. |
| `BillingPageTest.php:52` | `assertSee('$27/year')` → both `$49/year` and `$5.90/month`, and that both post to `billing.subscribe` with a plan. |
| `StructuredDataTest.php:60-71` | Two paid offers, one `MON` and one `ANN`, prices matching config. |
| `CrawlerDiscoveryTest.php:163` | Follows the new `PublicPages` wording. |

---

## Phase 5 — Legal and email copy

**Goal:** no page or email states a yearly period as if it were the only one, and
the refund policy survives contact with a monthly plan.

### `resources/views/terms-and-conditions.blade.php:75-84`

Clause 5 states one price "per year" and renewal "each year". Both become
plan-aware: both prices named, and renewal described as "at the end of each
billing period (monthly or yearly, whichever you chose)".

### `resources/views/refund-policy.blade.php:19-35`

§2 currently reads *"This applies to a first subscription and to each yearly
renewal."* Carried over verbatim to monthly, that promises a 14-day refund window
on **every** monthly charge — and since 14 days is roughly half a monthly period,
it would make a monthly subscription refundable essentially all the time.

**The window is limited to a first payment on each plan, not to every renewal.**
Rewrite §2 to say so plainly: 14 days from a customer's first payment on a plan,
in full, no reason needed. A renewal is not a fresh purchase — it is the
continuation of a contract the customer has already had a period to evaluate, and
cancelling before it lands is two clicks from the Subscription page.

Keep the sentence that follows unchanged: *"If you are a consumer in the EU or UK,
this reflects your statutory right of withdrawal; nothing in this policy limits
rights you have by law."* That right attaches to the **contract**, not to each
recurring charge, so scoping our window to the first payment is consistent with it
rather than a carve-out from it — and the sentence guarantees a customer with
stronger statutory rights keeps them regardless.

Then, so the narrowing does not read as a trap:

- §3 — "the remainder of a yearly period" becomes "the remainder of the period".
- §3's service-failure clause ("we will refund a fair share of the period
  affected") is unchanged and now does more work: it is the route for anyone whose
  complaint is about a renewal.
- The pricing page's *"you have 14 days to ask for a full refund"*
  (`pricing.blade.php:104-107`) must gain "on your first payment", or the two pages
  contradict each other — which is precisely the kind of mismatch a merchant of
  record's reviewer opens both tabs to find.
- Phase 10 of the go-live plan (pre-renewal notice) is the natural companion to
  this: if a renewal is no longer refundable, telling the customer it is coming
  stops being a courtesy and starts being the thing that makes the policy fair.

### `app/Notifications/RenewalPaymentFailed.php:35`

*"Your yearly renewal payment did not go through"* → "Your renewal payment…".
Simplest correct fix; the notification does not currently load the subscription
and does not need to for this sentence.

### `app/Notifications/TrialEndingSoon.php:51` and `TrialEnded.php:60`

Both call `SubscriptionPrice::perInterval()` in the action label. There is no plan
at trial time — the customer has not chosen one — so the action becomes plain
"Subscribe" / "Reactivate" and a preceding `->line()` names both prices. This is
the one place where dropping the price out of the button is right: a button that
names a price the customer has not agreed to is worse than one that does not.

### Tests

`BillingNotificationsTest` asserts on notification content; update the price
assertions and add one that neither trial email names a single plan as if it were
the only one.

---

## Phase 6 — Verification

Nothing above proves money moves. Against the AgentaOS **sandbox**:

- [ ] `php artisan agentaos:create-payment-link yearly` → id into `AGENTAOS_YEARLY_PAYMENT_LINK_ID`
- [ ] `php artisan agentaos:create-payment-link monthly` → id into `AGENTAOS_MONTHLY_PAYMENT_LINK_ID`
- [ ] The AgentaOS dashboard shows **$49/year** and **$5.90/month** — matching what the site displays
- [ ] Buy the yearly plan → `subscriptions.plan = 'yearly'`, `entitled_until ≈ +1 year +7 days`
- [ ] Buy the monthly plan → `subscriptions.plan = 'monthly'`, `entitled_until ≈ +1 month +7 days`, **not** a year
- [ ] `ResolveAgentaOsSubscription` lands and `current_period_end` matches the plan
- [ ] `php artisan billing:sync` leaves both rows and both dates untouched
- [ ] Cancel a monthly subscription → access holds to period end, no renewal
- [ ] Logged out, both prices are reachable from the homepage in one click
- [ ] `/pricing` structured data validates and carries three offers
- [ ] Grep: no `subscription.price`, no `billing_interval`, no `payment_link_id`, no
      hardcoded `27` and no bare "per year" outside a plan-aware expression

Then repeat the two link creations in **live** mode before go-live, because the
$27 link is still the one production would charge against.

---

## Open questions

**1. Grace on a monthly plan.** `grace_days = 7` exists to absorb the card
processor's retry window. That window does not shrink with the billing period, so
7 days is still the right number mechanically — but it is 23% of a monthly period,
and it means one $5.90 payment buys 37 days. Recommend keeping 7 for both and
revisiting only if it is abused. Making it per-plan is a config change away if not.

**2. Does the trial change?** Currently 7 days for everyone. With a $5.90 entry
price the trial is arguably doing less work than it was against a $27 commitment.
Out of scope for this plan; noted because the two decisions interact.

## What this plan does not do

- No proration, no mid-period plan switching, no upgrade path.
- No local reconciliation of the price stored on the AgentaOS link against config —
  Phase 6's checklist is the control, and it is a manual one.
- No change to quotas. Both plans get the same 5 dynamic / 50 static. If monthly
  is ever meant to be a smaller tier, that is a different plan than this one.
