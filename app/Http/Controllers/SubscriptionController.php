<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Enums\TrackedEvent;
use App\Models\Subscription;
use App\Services\AgentaOS\AgentaOsClient;
use App\Services\AgentaOS\AgentaOsException;
use App\Services\BillingAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function __construct(private readonly AgentaOsClient $agentaOs) {}

    /**
     * Opens an AgentaOS checkout for the signed-in user and sends them to it.
     */
    public function checkout(): RedirectResponse
    {
        $user = Auth::user();
        $plan = Plan::default();
        $linkId = $plan->paymentLinkId();

        if ($linkId === null) {
            BillingAlerts::raise(
                'payment-link-missing',
                $plan->paymentLinkEnvironmentVariable().' is not configured, so nobody can subscribe.',
            );

            return back()->with('error', 'Subscriptions are temporarily unavailable. Please try again later.');
        }

        try {
            $checkout = $this->agentaOs->createCheckout($user, $linkId);
        } catch (AgentaOsException $exception) {
            // A checkout that cannot be opened stops every sale, and the buyer
            // only sees a vague apology, so this must not sit in the log alone.
            BillingAlerts::raise(
                'checkout-creation-failed',
                'An AgentaOS checkout could not be created. Nobody hitting this can subscribe.',
                [
                    'user_id' => $user->getKey(),
                    'status' => $exception->status,
                    'request_id' => $exception->requestId,
                    'message' => $exception->getMessage(),
                ],
            );

            return back()->with('error', 'We could not start the checkout. Please try again in a moment.');
        }

        // Recorded before the redirect so the webhook has a row to complete,
        // whichever arrives first.
        Subscription::updateOrCreate(
            ['checkout_session_id' => $checkout['session_id']],
            [
                'user_id' => $user->getKey(),
                'status' => 'incomplete',
                'currency' => $checkout['currency'] ?? config('subscription.currency'),
            ],
        );

        TrackedEvent::CheckoutStarted->record();

        return redirect()->away($checkout['checkoutUrl']);
    }

    /**
     * Where AgentaOS returns the buyer after payment.
     *
     * This is a courtesy landing page, not a fulfilment path: the buyer may
     * close the tab before it loads. The webhook is the source of truth.
     */
    public function success(): RedirectResponse
    {
        TrackedEvent::CheckoutCompleted->record();

        return redirect()
            ->route('filament.admin.pages.dashboard')
            ->with('status', 'Thanks! Your payment is being confirmed. This usually takes a few seconds.');
    }

    public function cancel(): RedirectResponse
    {
        TrackedEvent::CheckoutAbandoned->record();

        return redirect()
            ->route('filament.admin.pages.dashboard')
            ->with('status', 'Checkout cancelled. You have not been charged.');
    }
}
