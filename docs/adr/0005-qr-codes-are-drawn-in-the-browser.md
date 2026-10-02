# QR codes are drawn in the browser

**Status:** accepted, not yet built. Decided on 2026-10-02 after the four-variant editor prototype on branch `prototype/qr-editor`.

A QR Code's image is drawn in the browser, from its Design, every time it is shown or downloaded. The server stores the Design and what the code encodes, and stores no image. There is one renderer, written in JavaScript; `simplesoftwareio/simple-qrcode` and the Imagick PNG path stop being used to draw codes.

The editor the Design work needs — fluid modules, custom corners and eyes, frames, a logo with padding, backing and clear space — could not be drawn on the server without a second renderer:

1. **Building our own SVG and rasterising it with Imagick does not survive.** Locally, ImageMagick reads SVG through its built-in MSVG reader, not librsvg. It drew gradients solid black, ignored `clipPath`, and threw on an embedded logo. Whether Laravel Cloud's ImageMagick has librsvg is unverified, and standard plans cannot add system packages.
2. **Custom BaconQrCode modules and eyes would work** (drawn by Bacon's own Imagick backend), but a live preview would then mean either a server round trip on every tweak or a second, JavaScript copy of the same geometry that could drift from the one that gets saved.
3. **Nothing on the server needs an image.** Every consumer of a stored image today is a browser page: the codes table, the Top performing widget, the View page and its downloads, the style picker, and the homepage and Landing Page generator. No email, API or PDF attaches a code.

So the browser renderer the prototype proved is the only one, and the image the Owner approves is, by construction, the image that is printed.

## Consequences

- `options['design']` holds the Design, versioned (`version: 1`), and nothing else. Format and size are chosen at download time, not stored on the code; error correction is derived from the Design (H with a logo, M otherwise), not stored. `qr_code_image` and the regenerate-on-save path go.
- The homepage promise — "we never store your code or its link" — becomes literal: the link no longer leaves the browser at all. The `StaticQrGenerated` count, which `/qr/instant` used to record as a side effect, has to be sent to `/events` by the page instead.
- Logos stay on the bucket and reach the browser through our own route, checked against the Owner, so the canvas that makes a PNG is not tainted by a cross-origin image. `Storage::get()`, never `Storage::path()`.
- The scan limits (contrast at least 3:1, logo at most 25% of the code) are enforced by the editor everywhere and re-validated by the server on save, because the server can no longer look at an image to know a code is readable.
- The server cannot produce an image on its own. "Email me my code", a public API or a per-code OG image would each need a server renderer added first. That is additive: the Design is the source of truth, and a PHP renderer can be written against it later without migrating anything.
- A visitor without JavaScript sees no code. The homepage generator already required JavaScript, so this changes nothing in practice.
