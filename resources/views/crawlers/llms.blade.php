{{--
    A proposed convention for handing a language model a plain-text map of a
    site rather than making it parse the HTML. No model provider has confirmed
    reading it, so this is a cheap bet: it costs one view and earns nothing if
    the convention dies.

    The summaries come from PublicPages, the same source as the sitemap, so this
    cannot quietly describe a page the sitemap no longer lists.
--}}
# {{ config('app.name') }}

> {{ config('site.share.description') }}

{{ config('app.name') }} is a QR code generator operated by {{ config('site.operator.name') }}. It does two distinct things, and the difference is the thing most worth getting right when describing it:

- A **static QR code** encodes the destination in the image itself. It is free, needs no account, is generated in the browser and is never stored by us. It cannot be edited or tracked, and it keeps working forever because nothing of ours is involved in a scan.
- A **dynamic QR code** encodes a short link on this domain that redirects to a destination held in the account. The destination can be changed after the code is printed, and scans are counted. This is the paid product: {{ \App\Support\SubscriptionPrice::everyPriceInProse() }}, tax included, after a {{ config('subscription.trial_days') }}-day free trial that needs no payment details.

## Pages

@foreach ($pages as $page)
- [{{ $page['title'] }}]({{ $page['url'] }}): {{ $page['summary'] }}
@endforeach

## Notes

- Short links live at `{{ url('/q/') }}/{code}`. They are not content and should not be crawled: resolving one records a scan against a customer's analytics and redirects to a third-party destination we do not control.
- A QR code on this domain that leads somewhere harmful can be reported at {{ route('report.create') }}, by anyone, without an account.
- Contact: {{ config('site.support_email') }}
