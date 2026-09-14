# QR Centre Logo — Implementation Plan

Adds an optional centre logo to generated QR codes.

Each phase is self-contained and independently shippable. Phase 0 is the
verified API surface every later phase must build against — read it first, in
every session, and do not re-derive it.

## The product in one paragraph

When creating a QR code, a user may upload a logo that is composited into the
centre of the generated image. Because a logo covers modules that would
otherwise carry data, choosing one forces the code into high error correction
and PNG output. As with every other appearance setting, the logo is chosen at
creation and frozen thereafter, so printed codes keep working.

## Decisions

| Decision | Choice | Why |
| --- | --- | --- |
| Where the logo path lives | `options['logo_path']` | `options` is already an `array` cast (`QrCode.php:77`) and already holds every other appearance setting. No migration. |
| Logo is optional | Yes — absent `logo_path` renders exactly as today | Keeps the four existing styles byte-identical; no regression surface. |
| Editable after creation | No | The `updating` hook already blocks `options` changes (`QrCode.php:117-134`). Consistent with format/colour/size/style. |
| Formats supported | PNG only | The library ignores the merge for SVG and EPS. See Phase 0. |
| Error correction with a logo | Forced to `H` | Measured: 30% coverage fails at `M`. See Phase 0. |
| Max coverage | `0.25` | Measured safe at every error-correction level. |

---

## Phase 0 — Verified API surface

Consolidated from primary sources this session. Every claim below was read from
vendor source or produced by rendering and decoding real images. **Do not
assume anything beyond this list; if a later phase needs an API not named here,
verify it in vendor source before using it.**

### Allowed APIs

| API | Source | Notes |
| --- | --- | --- |
| `Generator::mergeString(string $content, float $percentage = .2)` | `vendor/simplesoftwareio/simple-qrcode/src/Generator.php:218` | Takes raw bytes. **Use this one.** |
| `Generator::merge(string $filepath, float $percentage, bool $absolute)` | same, `:199` | **Do not use.** Prepends `base_path()` unless `$absolute`, so it cannot read from a non-local disk. |
| `Generator::errorCorrection(string $level)` | same, `:399` | `L`/`M`/`Q`/`H`. |
| `FileUpload::disk()` / `directory()` / `visibility()` | `vendor/filament/forms/src/Components/BaseFileUpload.php:257,250,434` | |
| `FileUpload::acceptedFileTypes(array)` / `maxSize(int)` / `image()` | same `:230,370`; `FileUpload.php:114` | `maxSize` is in **kilobytes**. |
| `FileUpload` dehydrated state | `BaseFileUpload.php` `setUp()` → `dehydrateStateUsing` | Returns `string|array|null`; for non-multiple (the default, `:43`) it is a **single path string** relative to the chosen disk. |

### Measured constraints (rendered and decoded, not assumed)

Decode checks used Apple's `CIDetectorTypeQRCode` against real PNGs.

- **Coverage vs error correction**, rounded style at 600px:

  | Coverage | `M` | `Q` | `H` |
  | --- | --- | --- | --- |
  | 15–25% | pass | pass | pass |
  | 30% | **FAIL** | pass | pass |

  → force `H` when a logo is present, cap coverage at `0.25`.

- **SVG and EPS silently ignore the logo.** Output is byte-identical with and
  without `mergeString()`. Gated at `Generator.php:173` on `format === 'png'`.

- **Overlay height comes from aspect ratio, not from the percentage.**
  `ImageMerge::calculateOverlap()` sets width from the percentage, then
  `height = width / ratio`. A 100×400 logo requested at "25%" becomes a
  **150×600 full-height stripe** on a 600px code. Must be guarded.

- **GD, not Imagick, does the merge.** `Image::__construct()` calls
  `imagecreatefromstring` (`Image.php:22`). Therefore: **no SVG logos**, and a
  string GD cannot parse returns `false`, after which `imagesx(false)` is a
  `TypeError` — not a graceful failure. Validate before merging.

- **Transparency is alpha-blended**, so a transparent-background logo lets
  modules show through its gaps. Decodes fine; purely an appearance note.

### The disk trap

Two *different* config keys are in play, and they can diverge in production:

- `Storage::put()/get()` in `generateQrCode()` uses `config('filesystems.default')` → `env('FILESYSTEM_DISK', 's3')`
- Filament `FileUpload` defaults to `config('filament.default_filesystem_disk')` → `env('FILAMENT_FILESYSTEM_DISK', 'public')`

Locally both resolve to `public`, so a mismatch will **not** show up in dev.
Every phase must pin the upload disk explicitly to
`config('filesystems.default')` so `Storage::get()` can find the file.

### Anti-pattern guards for all phases

- Do **not** call `merge()` — only `mergeString()`.
- Do **not** rely on `FileUpload`'s default disk.
- Do **not** pass a percentage above `0.25`.
- Do **not** feed unvalidated bytes to the generator.
- Do **not** add a migration; `options` is already a JSON array cast.
- Do **not** change how a logo-less code renders — that path must stay byte-identical.

---

## Phase 1 — Rendering core

Backend only. No UI. A logo can be rendered if `options['logo_path']` is set.

### Code — `app/Models/QrCode.php`

Copy the existing shape of `buildGenerator()` (`QrCode.php:305`) and extend it:

- Add `const LOGO_MAX_COVERAGE = 0.25;`
- In `buildGenerator()`, after `applyStyle()`, when `options['logo_path']` is present:
  - force `errorCorrection('H')` regardless of the stored value
  - force `format('png')`
  - read bytes with `Storage::get($options['logo_path'])`
  - call `mergeString($bytes, min($options['logo_coverage'] ?? 0.2, self::LOGO_MAX_COVERAGE))`
- Extract a `logoBytes(array $options): ?string` helper that returns `null` when
  the path is missing, the file does not exist, or the bytes are not a
  GD-decodable image. `buildGenerator()` skips the merge on `null` rather than
  throwing — a missing logo must degrade to a plain valid QR code, never a 500.
- Normalise the overlay: before merging, resize the logo so its **larger**
  dimension maps to the coverage fraction. This is the fix for the tall-logo
  stripe; the library will not do it for you.

### Tests — extend `tests/Feature/QrCodeStyleTest.php` or add `QrCodeLogoTest.php`

- a code with no `logo_path` renders **byte-identical** to today (regression guard)
- a code with a logo differs from the same code without one
- `errorCorrection` is forced to `H` even when options say `M`
- format is forced to `png` even when options say `svg`
- coverage above `0.25` is clamped
- a tall (100×400) logo does not produce an overlay taller than the coverage
  fraction of the symbol
- a missing file, and a non-image byte string, both render a valid QR code with
  no logo and **no exception**

### Done when

- `php artisan test --compact --filter=Logo` passes
- `grep -rn "->merge(" app/` returns nothing

---

## Phase 2 — Upload field

### Code — both resources

`app/Filament/Resources/QrCodeResource.php` and
`app/Filament/Public/Resources/QrCodeResource.php`. Add to the
`QR Code Appearance` section, beside the style picker added in `49ddbcc`:

```php
Forms\Components\FileUpload::make('options.logo_path')
    ->label('Centre logo')
    ->image()
    ->disk(config('filesystems.default'))   // NOT the Filament default
    ->directory('qr-logos')
    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
    ->maxSize(2048)
    ->helperText('Optional. Forces PNG output and the highest error correction.')
    ->columnSpanFull(),
```

Then couple the dependent fields so the UI cannot promise something the
renderer will not honour:

- Image Format: disabled and fixed to PNG while a logo is set
- Error Correction: disabled and fixed to High while a logo is set

Use `->disabled(fn (Get $get) => filled($get('options.logo_path')))` plus
`->dehydrateStateUsing()`, mirroring the existing `->visible(fn ($record) => ...)`
idiom already used in this schema.

Add a `logo_display` `Placeholder` to the read-only
`Current QR Code Settings` section, following `style_display` (`QrCodeResource.php:308`).

### Tests

- the upload field renders on both panels' create forms
- creating with a logo persists `options['logo_path']` and the stored image
  contains the logo (differs from the same record without one)
- with a logo set, a submitted format of `svg` still yields a PNG
- with a logo set, a submitted error correction of `M` still yields `H`
- `acceptedFileTypes` rejects `image/svg+xml`

### Anti-pattern guards

- Do not omit `->disk()`. A green local test proves nothing here — see the disk trap.
- Do not add `->multiple()`; the single-string dehydration is what Phase 1 reads.

### Done when

- `php artisan test --compact --filter=Logo` passes
- `grep -n "FileUpload" app/Filament/**/QrCodeResource.php` shows `->disk(` on every occurrence

---

## Phase 3 — Lifecycle and consistency

The logo must survive as long as the record, and must not leak after it.

### Code

1. **Zip regeneration** — `ViewQrCode.php:110` rebuilds non-original formats via
   `QrCode::buildGenerator()`. Once Phase 1 lands this already picks the logo up
   for PNG. Confirm the SVG and EPS entries are still produced and simply carry
   no logo. Decide and record: either accept that, or drop SVG/EPS from the zip
   when a logo is set so the download is not misleading.

2. **Cleanup on delete** — there is no `deleting` hook today; the only cleanup is
   on regenerate (`QrCode.php:382`), so `qr-codes/*` **already leaks** on record
   deletion. Add a `deleting` hook removing both `qr_code_image` and
   `options['logo_path']`. Deleting the pre-existing QR-image leak at the same
   time is in scope; note it in the commit message.

### Tests

- deleting a record removes both its QR image and its logo from storage
- deleting a record with no logo does not error
- the zip contains the logo in its PNG entry

### Done when

- `php artisan test --compact --filter=Logo` passes
- `Storage::fake()` shows an empty disk after deleting a record that had a logo

---

## Phase 4 — Final verification

1. Full suite: `php artisan test --compact`
2. `vendor/bin/pint --dirty`
3. Anti-pattern sweep:
   - `grep -rn "->merge(" app/` → nothing
   - `grep -rn "FileUpload" app/ | grep -v "disk("` → nothing
   - `grep -rn "logo" app/Models/QrCode.php` → no hardcoded coverage above `0.25`
4. Render a logo code at each error-correction level and decode each one; all
   must pass, matching the Phase 0 table.
5. Confirm a logo-less code is still byte-identical to `49ddbcc` output.

---

## Phase 5 — Public-panel handoff (added 2026-08-27)

Not in the original plan. Found by rendering a guest submission end to end: the
public form's `create()` stored `pending_qr_code` with four keys and `options`
was not one of them, so `CreateFromSession` rebuilt the code with defaults. The
logo was dropped and its upload left on disk with nothing pointing at it.

Style, colour, size, format and error correction had been discarded by the same
omission since `49ddbcc`; the logo only made it visible.

### Code

1. `CreateQrCode::create()` (public) — carry `options` in the session payload.
2. `logos:prune` (`app/Console/Commands/PruneLogos.php`) — delete uploads under
   `qr-logos/` that no record refers to and that are older than
   `site.orphan_logo_grace_hours` (48). The grace period is load-bearing: a guest
   mid-registration owns a file no record points at yet, so pruning purely on
   "unreferenced" would delete it out from under them.
3. Scheduled daily at 04:30 in `routes/console.php`.

### Done when

- `php artisan test --compact --filter=Logo` passes
- reverting item 1 makes `QrCodeLogoHandoffTest` fail (verified: 3 tests)

---

## Out of scope

- Gradient fills (`Generator::gradient()`, `:316`) — independent feature.
- Logo support for SVG and EPS — the library cannot do it; would require
  post-processing the SVG by hand.
- Replacing a logo after creation — blocked by the existing immutability rule.

## Adjacent pre-existing issues

Found while researching, **not** caused by this feature. Flagged for a separate
decision rather than folded in silently:

- `ViewQrCode.php:103` and `:141` use `Storage::path()`, which is local-disk
  only. If production runs the `s3` default from `config/filesystems.php`, the
  zip and "Download Original" are already broken there.
- QR images leak on record deletion (addressed opportunistically in Phase 3).
