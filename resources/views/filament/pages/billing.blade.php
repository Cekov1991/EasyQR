@php
    $user = auth()->user();
    $state = $user->accountState();
    $subscription = $this->getSubscription();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Current plan</x-slot>

        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-3">
                <x-filament::badge
                    :color="match ($state->value) {
                        'subscribed' => 'success',
                        'trialing' => 'warning',
                        default => 'danger',
                    }"
                >
                    {{ $state->label() }}
                </x-filament::badge>

                @if ($state->value === 'trialing')
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $user->trialDaysRemaining() }}
                        {{ \Illuminate\Support\Str::plural('day', $user->trialDaysRemaining()) }}
                        remaining, ends {{ $user->trial_ends_at?->format('j F Y') }}
                    </span>
                @elseif ($state->value === 'subscribed')
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        @if ($subscription?->cancel_at_period_end)
                            Cancelled, access until {{ $user->entitled_until?->format('j F Y') }}
                        @else
                            Renews {{ $subscription?->current_period_end?->format('j F Y') ?? 'yearly' }}
                        @endif
                    </span>
                @else
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Dynamic QR codes are not resolving
                    </span>
                @endif
            </div>

            @if ($subscription?->isPastDue())
                <p class="text-sm text-danger-600 dark:text-danger-400">
                    Your last renewal payment failed and is being retried. Your QR codes keep
                    working until {{ $user->entitled_until?->format('j F Y') }}.
                </p>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Dynamic QR codes</p>
                    <p class="font-medium">
                        {{ $user->quota()->usedFor('dynamic') }} of {{ $user->quota()->limitFor('dynamic') }} used
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Static QR codes</p>
                    <p class="font-medium">
                        {{ $user->quota()->usedFor('static') }} of {{ $user->quota()->limitFor('static') }} used
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>

    @unless ($state->value === 'subscribed')
        <x-filament::section>
            <x-slot name="heading">
                {{ $state->value === 'lapsed' ? 'Reactivate' : 'Subscribe' }}
            </x-slot>

            <x-slot name="description">
                {{ $this->getFormattedPrice() }} per year, billed once. Tax included. AgentaOS is
                the merchant of record and handles VAT for your country.
            </x-slot>

            <div class="flex flex-col gap-4">
                <ul class="flex flex-col gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <li>Up to {{ config('subscription.quotas.dynamic') }} dynamic QR codes, editable after printing</li>
                    <li>Up to {{ config('subscription.quotas.static') }} static QR codes</li>
                    <li>Scan analytics for device, browser and country</li>
                </ul>

                <form method="POST" action="{{ route('billing.subscribe') }}">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $this->getOfferedPlan()->value }}">
                    <x-filament::button type="submit" size="lg">
                        {{ $state->value === 'lapsed' ? 'Reactivate' : 'Subscribe' }}
                        for {{ $this->getFormattedPrice() }}/year
                    </x-filament::button>
                </form>

                @if (session('error'))
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ session('error') }}</p>
                @endif
            </div>
        </x-filament::section>
    @endunless
</x-filament-panels::page>
