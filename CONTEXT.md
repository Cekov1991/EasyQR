# MaystQR

A QR code generator where signed-up users manage a personal library of QR codes. Static codes are free forever; dynamic codes — the ones whose destination can be changed after printing — require a paid **Subscription** once the 7-day **Trial** ends.

## Language

### QR codes

**Static QR Code**:
A QR code whose image encodes the destination content directly, so scanning never touches MaystQR's servers.
_Avoid_: permanent QR, free QR

**Dynamic QR Code**:
A QR code whose image encodes a MaystQR short URL (`/q/{shortUrl}`), so every scan is resolved — and counted — by MaystQR before redirecting.
_Avoid_: trackable QR, editable QR (both are consequences, not the definition)

**Short URL**:
The 8-character public identifier in a Dynamic QR Code's encoded URL, permanent for the life of the code because the printed image cannot be reissued.
_Avoid_: slug, code, token

**Scan**:
One resolution of a Dynamic QR Code through `/q/{shortUrl}`, logged with device, browser, and geolocation. Static QR Codes produce no Scans by construction.

### Site

**Landing Page**:
A public page written for one search a stranger types — a problem ("QR code stopped working") or a use case ("QR code for a restaurant menu") — that lets them make a Static QR Code on the spot and explains when their case needs a Dynamic one. Exists only once a human has read and published it.
_Avoid_: SEO page, LP (in prose; `lp-` survives only as a ref prefix if one is ever needed)

**Signup Source**:
Which of our own links a User registered through — the offer, the quiet inline link, or a Landing Page's call to action. Recorded once, at registration, from a closed set; anything we did not publish reads as unknown. Says nothing about which page the link sat on.
_Avoid_: referrer (that is the browser's header), campaign, attribution (too broad — this is one fact, not a model)

**Signup Landing Page**:
The Landing Page a User's Signup Source link sat on, or none when they registered from anywhere else. Last click only: a visitor who leaves a Landing Page before registering is not credited to it.

### People

**Owner**:
The User who created a QR Code and whose billing state decides whether it resolves.

**Scanner**:
Whoever points a phone at a QR Code — almost always a stranger with no MaystQR account, and never assumed to be the Owner.
_Avoid_: user (ambiguous: a Scanner cannot pay the Owner's Subscription)

### Billing

**Trial**:
The 7-day free period that begins when a User registers, during which their Dynamic QR Codes resolve normally. Account-level and card-free — MaystQR grants it, the payment provider knows nothing about it.
_Avoid_: free plan, freemium (the Trial ends; static codes stay free independently)

**Plan**:
What a User buys: a billing interval and a price under one name. Two exist, **Monthly** and **Yearly**. A Plan owns its interval by construction — a Plan named monthly cannot bill yearly — while its price and its Payment Link are configuration. Yearly is the default and the recommended Plan wherever both prices are shown, always with its saving against twelve monthly charges stated beside them. The homepage teaser is the exception: it quotes the cheapest Plan with a "from" and leaves the comparison to the pricing page. There is no switching between Plans, only cancelling one and buying the other.
_Avoid_: tier, package (both plans carry the same Quota), billing interval (that is one property of a Plan, not the Plan)

**Subscription**:
A User's paid commitment to one Plan that keeps their Dynamic QR Codes resolving, created at AgentaOS when the User pays that Plan's Payment Link. The Subscription records which Plan bought it, and the period it grants is that Plan's interval.
_Avoid_: membership, package; "plan" as a synonym for the Subscription itself (a Subscription is *to* a Plan)

**Payment Link**:
The reusable AgentaOS product (`type: subscription`, with the amount and `billingInterval` fixed on it at creation) that a User pays to start a Subscription. One link exists per Plan, not one per User. Because the charged amount lives on the link, changing a Plan's price means recreating its link.
_Avoid_: checkout URL (that is what a Checkout produces)

**Checkout**:
One AgentaOS session created from the Payment Link for one specific User, carrying that User's identity in `metadata` so the resulting payment can be attributed.
_Avoid_: order, session (ambiguous with Laravel's HTTP session)

**Entitlement**:
A User's currently-held right to have their Dynamic QR Codes resolve, held locally as a date MaystQR owns rather than a live question asked of AgentaOS. Granted by the Trial, extended by a Subscription, and outlived by a 7-day grace after the paid period ends.
_Avoid_: access, permission (those mean authorization, not billing state)

**Quota**:
The ceiling on how many QR Codes of each kind a User may create — 5 Dynamic, 50 Static by default. It gates creation only, and never stops an existing code from resolving.
_Avoid_: limit (unqualified), plan features

### Account states

**Trialing**:
Within the 7 days after registration. Dynamic QR Codes resolve; no card has been given.

**Subscribed**:
Holding an Entitlement paid for through AgentaOS. Dynamic QR Codes resolve.

**Lapsed**:
The Trial ended and no Subscription covers today. Dynamic QR Codes stop resolving; Static QR Codes, the library, and past Scan analytics stay fully available.
_Avoid_: expired (that word belonged to the retired per-code model), suspended, cancelled (cancelled is an AgentaOS Subscription status, not an account state)

## Relationships

- A **User** has at most one **Subscription** and exactly one **Trial**
- A **Subscription** is to exactly one **Plan**, and the **Plan** fixes its billing interval
- A **User** owns many **QR Codes**, each either **Static** or **Dynamic**, bounded by their **Quota**
- A **Dynamic QR Code** has many **Scans**; a **Static QR Code** has none
- A **Checkout** belongs to one **User** and is created from one **Plan**'s **Payment Link**
- An **Entitlement** belongs to a **User**, never to a **QR Code**
- A **User** has at most one **Signup Source** and at most one **Signup Landing Page**; a Signup Landing Page never appears without the Signup Source that was clicked on it

## Example dialogue

> **Dev:** "A user's trial ended yesterday and they never paid. What happens when someone scans one of their codes?"
> **Domain expert:** "Depends which kind. Their **Static QR Codes** keep working forever — the phone reads the destination straight off the image, we're not even in the loop. Their **Dynamic QR Codes** hit `/q/{shortUrl}`, we see the **Owner** has no **Entitlement** covering today, and the **Scanner** gets a page saying the code is inactive."
> **Dev:** "Does the scanner get told to pay?"
> **Domain expert:** "No — the **Scanner** is a stranger, they can't pay someone else's **Subscription**. They see a neutral page. The **Owner** gets the reactivate prompt, by email and in the panel."
> **Dev:** "So the trial is per code or per account?"
> **Domain expert:** "Per account. One **Trial** per **User**, seven days from registration. It doesn't matter when each code was made."

## Flagged ambiguities

- "expires" was overloaded — the `qr_codes.expires_at` column implemented a **per-code** 7-day trial extendable by buying a `QrCodePackage`. Resolved: expiry is now **account-level** only; a Dynamic QR Code's scannability follows its **Owner**'s **Entitlement**, and the per-code package product is retired.
- "subscription" was used for the abandoned PayPal scaffolding (`App\Models\Subscription`, `PayPalService::createSubscription()`, which actually created a one-time order). Resolved: **Subscription** means the AgentaOS recurring commitment to a **Plan**; the PayPal code is deleted.
- "yearly" was baked into the definition of **Subscription** and **Payment Link** while only one plan existed, and the billing interval was a global config value. Resolved: the interval is a property of the **Plan** (see ADR-0003), and both terms are now stated per Plan.
- "user" was used for both the account holder and the person scanning. Resolved: **Owner** holds the account and pays; **Scanner** holds the phone and pays nothing.
