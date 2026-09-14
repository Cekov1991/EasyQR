<?php

namespace App\Support;

use App\Models\QrCode;

/**
 * The questions people ask before they buy, and after they have paid.
 *
 * Held as data rather than written into the Blade view because two consumers
 * need the same words: the page a person reads, and the FAQPage JSON-LD a
 * search engine or an assistant quotes back at them. A question answered one
 * way in the markup and another way in the prose is worse than no markup at
 * all — it is a wrong answer with a machine's confidence behind it.
 *
 * Every price, quota and period is interpolated from config for the same
 * reason the pricing page interpolates them: an answer that says five dynamic
 * codes while the quota says ten is a support ticket we wrote ourselves.
 *
 * Answers are HTML because several of them have to point at the policy that
 * governs them. StructuredData strips the tags for the JSON-LD, so anything
 * written here must still read correctly as plain text — say "our Refund
 * Policy" rather than "here".
 */
class Faq
{
    /**
     * Every question, grouped as the page presents them.
     *
     * @return array<int, array{title: string, questions: array<int, array{id: string, question: string, answer: string}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Static or dynamic',
                'questions' => self::choosing(),
            ],
            [
                'title' => 'Making and printing codes',
                'questions' => self::making(),
            ],
            [
                'title' => 'Trial, payment and cancelling',
                'questions' => self::billing(),
            ],
            [
                'title' => 'Limits, data and your account',
                'questions' => self::account(),
            ],
        ];
    }

    /**
     * Every question in one flat list, for the consumers that do not care how
     * the page groups them.
     *
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    public static function questions(): array
    {
        return array_merge(...array_column(self::sections(), 'questions'));
    }

    /**
     * The distinction the whole product rests on, asked four ways because
     * people arrive at it from four directions. The expensive mistake is a
     * static code on a print run, so every answer here is written to be read
     * before the order goes in rather than after.
     *
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    private static function choosing(): array
    {
        return [
            [
                'id' => 'static-vs-dynamic',
                'question' => 'What is the difference between a static and a dynamic QR code?',
                'answer' => <<<'HTML'
                    <p>
                        A <strong>static</strong> code carries its destination inside the image. The phone
                        reads the link straight off the pattern and goes there, so nothing of ours is
                        involved in the scan. That is why it is free, needs no account and keeps working
                        forever — and also why nothing about it can be changed or counted afterwards.
                    </p>
                    <p>
                        A <strong>dynamic</strong> code carries a short link on our domain instead. Every
                        scan reaches us first, we look up where that code should point today, and we send
                        the phone on. That round trip is what buys you an editable destination and a count
                        of who scanned it.
                    </p>
                    HTML,
            ],
            [
                'id' => 'edit-after-printing',
                'question' => 'Can I change where a code points after it is printed?',
                'answer' => <<<'HTML'
                    <p>
                        With a dynamic code, yes — as often as you like, and the printed image never
                        changes. The short link inside the pattern is fixed for the life of the code;
                        only the destination behind it moves.
                    </p>
                    <p>
                        With a static code, no. The link is part of the pattern, so changing it means
                        generating a different code and reprinting everything the old one is on.
                    </p>
                    HTML,
            ],
            [
                'id' => 'static-to-dynamic',
                'question' => 'Can I turn a static code I already printed into a dynamic one?',
                'answer' => <<<'HTML'
                    <p>
                        No. They are different images, so anything already printed goes on pointing where
                        it always did. This is the one decision worth making before a print run rather
                        than after: if there is any chance the destination will move, print a dynamic code.
                    </p>
                    HTML,
            ],
            [
                'id' => 'static-expiry',
                'question' => 'Do static QR codes expire?',
                'answer' => <<<'HTML'
                    <p>
                        Never. A static code keeps working with or without an account, whether or not you
                        ever subscribe, and whether or not we are still here. Scanning one does not
                        contact us, so there is nothing for us to switch off.
                    </p>
                    HTML,
            ],
            [
                'id' => 'static-recover',
                'question' => 'I generated a static code and closed the page. Can I get it back?',
                'answer' => <<<'HTML'
                    <p>
                        Not from us — the free generator never stores your code or its link, which is
                        deliberate and is why it needs no account. Paste the same link in again and you
                        get the same image: the pattern is worked out from the link, not looked up.
                    </p>
                    HTML,
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    private static function making(): array
    {
        $types = self::contentTypes();

        return [
            [
                'id' => 'content-types',
                'question' => 'What can a QR code contain?',
                'answer' => <<<HTML
                    <p>
                        The free generator on our home page makes a code for a web link. Inside your
                        account a code can also be one of these: {$types}. Someone scanning one gets the
                        right thing for its kind — a Wi-Fi network to join, a contact to save, a message
                        ready to send.
                    </p>
                    HTML,
            ],
            [
                'id' => 'logo-and-colour',
                'question' => 'Can I add a logo and change the colours, and will it still scan?',
                'answer' => <<<'HTML'
                    <p>
                        Yes. Codes in your account take a colour of your choosing, one of four patterns,
                        and a logo in the middle.
                    </p>
                    <p>
                        A centre logo covers modules that would otherwise carry data, so we hold it to a
                        quarter of the code at most and raise the error correction to its highest level
                        automatically whenever one is present. That is what keeps a code with a logo
                        readable. A code with a logo is produced as a PNG, because the logo is composited
                        into the image.
                    </p>
                    <p>
                        Keep the code dark on a light background rather than the other way round, and scan
                        the finished artwork yourself before it goes anywhere.
                    </p>
                    HTML,
            ],
            [
                'id' => 'printing',
                'question' => 'What should I check before printing?',
                'answer' => <<<'HTML'
                    <p>
                        <strong>Use the SVG</strong> if your printer accepts one. It is vector, so it stays
                        sharp at any size, from a business card to a billboard. The PNG is there for
                        everything that wants a picture instead.
                    </p>
                    <p>
                        <strong>Size it for the distance.</strong> A rough rule that holds up well: the code
                        wants to be about a tenth as wide as the distance it is scanned from — two
                        centimetres for a leaflet in someone's hand, a good deal more for a poster read
                        across a room.
                    </p>
                    <p>
                        <strong>Leave the margin alone.</strong> The clear border around the pattern is part
                        of the code, and cropping it to fit a layout is the most common reason a printed
                        code will not scan.
                    </p>
                    <p>
                        <strong>Test the proof, not the screen.</strong> Scan the actual printed piece with
                        more than one phone before the run goes ahead.
                    </p>
                    HTML,
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    private static function billing(): array
    {
        $trialDays = (int) config('subscription.trial_days');
        $graceDays = (int) config('subscription.grace_days');
        $prices = SubscriptionPrice::everyPriceInProse();
        $refundPolicy = url('/refund-policy');
        $support = config('site.support_email');

        return [
            [
                'id' => 'free-trial',
                'question' => 'Is the free trial really free?',
                'answer' => <<<HTML
                    <p>
                        Yes. Every new account gets {$trialDays} days of the full paid service and we ask
                        for no payment details to start it. Nothing is charged when it ends — there is no
                        card on file to charge. If you decide against subscribing, your dynamic codes
                        simply stop resolving and everything you made stays in your account.
                    </p>
                    HTML,
            ],
            [
                'id' => 'who-charges',
                'question' => 'Who charges me, and is tax included?',
                'answer' => <<<HTML
                    <p>
                        Dynamic codes cost {$prices}, and tax is included in that figure: AgentaOS is the
                        merchant of record for every purchase and settles VAT or sales tax for your
                        country out of it. The price you see is the total you pay, with nothing added at
                        checkout.
                    </p>
                    <p>
                        Your invoice and receipt come from AgentaOS, and the charge appears on your
                        statement under their name rather than ours. If you are looking at a line on your
                        statement you do not recognise, that is what it is.
                    </p>
                    HTML,
            ],
            [
                'id' => 'switch-plans',
                'question' => 'Can I switch between monthly and yearly?',
                'answer' => <<<HTML
                    <p>
                        Not in a single step, and we would rather say so than bury it. There is no switch
                        button: you cancel the plan you are on and subscribe to the other one. Cancelling
                        leaves your access running to the end of the period you have already paid for, so
                        nothing goes dark in between.
                    </p>
                    <p>
                        If the timing is awkward — you have just paid for a year and want to be on the
                        monthly plan, or the reverse — email us at <a href="mailto:{$support}">{$support}</a>
                        and we will sort it out rather than leave you to it.
                    </p>
                    HTML,
            ],
            [
                'id' => 'cancelling',
                'question' => 'How do I cancel?',
                'answer' => <<<'HTML'
                    <p>
                        From the Subscription page in your account, in two clicks. Cancelling stops the
                        next renewal and nothing else: you keep the full service until the end of the
                        period you have paid for, and your codes, destinations and scan history all stay
                        where they are.
                    </p>
                    HTML,
            ],
            [
                'id' => 'refunds',
                'question' => 'Can I get a refund?',
                'answer' => <<<HTML
                    <p>
                        If you change your mind within 14 days of your first payment on a plan, email us
                        from the address on your account and we will refund it in full, no reason needed.
                    </p>
                    <p>
                        Renewals sit outside that window: a renewal continues a subscription you have
                        already had a full billing period to judge, and stopping one before it is charged
                        takes two clicks. The details, including what happens if we are at fault, are in
                        our <a href="{$refundPolicy}">Refund Policy</a>. If you are a consumer in the EU
                        or the UK, nothing there limits the rights you have by law.
                    </p>
                    HTML,
            ],
            [
                'id' => 'stop-paying',
                'question' => 'What happens to my codes if I stop paying?',
                'answer' => <<<HTML
                    <p>
                        Your <strong>static codes are untouched</strong>. They never depended on us and
                        they keep working permanently.
                    </p>
                    <p>
                        Your <strong>dynamic codes stop redirecting</strong>. Anyone who scans one sees a
                        plain notice that the code is not active — they are not shown a bill or asked to
                        pay anything, because they are a stranger who scanned a poster. Your codes, their
                        destinations and all of your past analytics are kept, and every code starts
                        resolving again the moment you subscribe.
                    </p>
                    <p>
                        A failed renewal is not instant either. We add {$graceDays} days on the end of a
                        paid period, so a card that needs replacing does not darken everything you have
                        already printed while your bank retries it.
                    </p>
                    HTML,
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    private static function account(): array
    {
        $dynamic = (int) config('subscription.quotas.dynamic');
        $static = (int) config('subscription.quotas.static');
        $retention = (int) config('site.scan_retention_months');
        $privacyPolicy = url('/privacy-policy');
        $support = config('site.support_email');

        return [
            [
                'id' => 'how-many-codes',
                'question' => 'How many codes can I create?',
                'answer' => <<<HTML
                    <p>
                        A subscription covers {$dynamic} dynamic codes and {$static} saved static codes.
                        The free generator on our home page is unlimited and counts towards neither,
                        because nothing it makes is saved.
                    </p>
                    <p>
                        These ceilings apply to making codes, never to scanning them. Reaching the limit
                        stops you creating the next code; it never stops a code you have already printed
                        from working.
                    </p>
                    HTML,
            ],
            [
                'id' => 'more-codes',
                'question' => "What if I need more than {$dynamic} dynamic codes?",
                'answer' => <<<HTML
                    <p>
                        Ask us. The ceiling is set per account and we can raise yours — email
                        <a href="mailto:{$support}">{$support}</a> with a rough idea of how many you need
                        and what for.
                    </p>
                    HTML,
            ],
            [
                'id' => 'scan-data',
                'question' => 'What do you record when someone scans my code?',
                'answer' => <<<HTML
                    <p>
                        For each scan of a dynamic code: the date and time, the approximate country,
                        the device type, operating system and browser the phone reports, and the referring
                        page where there is one. You see all of it, as totals and as individual scans.
                    </p>
                    <p>
                        We do not store the IP address of anyone who scans a code, and nothing recorded is
                        tied to a name or used to recognise the same person across your codes or on a
                        later visit. Scan records are deleted automatically after {$retention} months.
                        Static codes produce no scan data whatsoever — we never learn that the scan
                        happened. Our <a href="{$privacyPolicy}">Privacy Policy</a> says all of this at
                        greater length.
                    </p>
                    HTML,
            ],
            [
                'id' => 'delete-account',
                'question' => 'What happens if I delete my account?',
                'answer' => <<<'HTML'
                    <p>
                        Everything goes with it: your dynamic codes stop resolving permanently, their short
                        links are never reissued to anyone else, and your scan history is deleted. Static
                        codes you have already downloaded or printed carry on working, because they never
                        depended on the account.
                    </p>
                    <p>
                        If what you actually want is to stop paying, cancel instead — that keeps the codes
                        and the history, and leaves them ready to resume.
                    </p>
                    HTML,
            ],
        ];
    }

    /**
     * "Wi-Fi, email, WhatsApp and four more" — the content types, read off the
     * model rather than listed here, so a type that is added or commented out
     * cannot leave this page advertising something the form does not offer.
     */
    private static function contentTypes(): string
    {
        $labels = collect(QrCode::QR_CONTENT_TYPES)
            /*
             * The labels carry a leading emoji for the select menu in the panel.
             * It is navigational furniture there and noise in a sentence.
             */
            ->map(fn (string $label): string => trim(preg_replace('/^[^\p{L}]+/u', '', $label)))
            ->reject(fn (string $label): bool => strtolower($label) === 'website')
            ->values();

        $last = $labels->pop();

        return $labels->implode(', ').' and '.$last;
    }
}
