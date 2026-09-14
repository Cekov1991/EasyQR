# The plan is a property of the subscription

**Status:** accepted. The `Plan` enum, per-plan config and plan-aware pricing are in place, and a checkout must name its Plan: the chosen Plan selects the payment link, is stored on `subscriptions.plan`, and is what the paid-webhook grant reads one period of. A row without a Plan — a checkout this app did not open — is granted on the configured default. The billing page still offers yearly only until the customer can choose, per [the two-plans implementation plan](../subscription-plans-implementation-plan.md).

Which Plan a customer bought — and therefore how long one payment entitles them for — is recorded on the Subscription row and read from there. It is never read from a global config value, and the billing interval is not configurable at all: it is fixed per Plan in code (`App\Enums\Plan::interval()`), by `match`. Prices and AgentaOS payment link ids stay in config, keyed per Plan, because they change without a deploy.

This is the second time the "which period" question has caused a defect, and the shape of both is the same: two places each independently knew the period, and nothing made them agree.

1. **`18f75cb`** — config named the billing interval while `GrantSubscriptionEntitlement` hardcoded `addYear()`. They agreed only because both said a year.
2. **Introducing a monthly plan** — the grant job read the interval from config and granted a provisional year, while AgentaOS was to bill a month. The follow-up reconciliation could not repair it: per ADR-0002, `User::grantEntitlementThrough()` refuses to shorten an existing date, deliberately, so a stale reply can never darken a paying customer's printed codes. A monthly buyer would have been granted a year that nothing in the system could ever correct.

The second defect is the one that forces this decision. ADR-0002's "dates only move forward" rule is right and stays. It means the first provisional grant must already be correct, and the only thing that knows the correct period at that moment is the Subscription being paid for. So the row must carry its Plan, and the grant must read the row.

## Consequences

- `Plan` is the unit the customer chooses, checkout carries, the Subscription stores, and the grant reads. There is no separate "interval" concept to configure; `BillingInterval` is reached only through a Plan.
- A Plan named monthly cannot be configured to bill yearly. The two facts that drifted apart in `18f75cb` are now one `match` arm.
- Every price-rendering method takes a **required** Plan. A default would let a call site compile while quietly quoting the wrong plan in copy that has to name both.
- The old single `subscription.price` and `subscription.billing_interval` keys are deleted, not aliased. A stale reader breaks loudly rather than quietly charging the old price.
- `subscriptions.plan` is nullable with no default and no backfill. A row without a Plan is a payment we did not initiate, and it should be visible as an anomaly rather than dressed up as a yearly sale. Code that needs a value asks for "plan or default".
- The price a buyer is *charged* is fixed on the AgentaOS payment link when it is created. Config governs what the site *displays*. Nothing reconciles the two; changing a Plan's price is therefore a two-step operation — recreate the link, then change config — and the go-live checklist is the control.
