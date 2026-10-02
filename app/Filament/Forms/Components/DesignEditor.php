<?php

namespace App\Filament\Forms\Components;

use App\Rules\ValidQrDesign;
use App\Support\QrDesignOptions;
use Filament\Forms\Components\Field;

/**
 * The Design editor (prototype variant A, "Studio"). Its state is the Design, so
 * Filament's validation, Quota check and save stay where they are, and the
 * server's Design validator runs as its rule. The code is drawn in the browser
 * by the one renderer; this field only hands the browser what to encode.
 *
 * The page it sits on offers the content through a public `encodedContent()`
 * method (see PreviewsEncodedContent), which the browser calls, debounced,
 * as the other fields change.
 */
class DesignEditor extends Field
{
    protected string $view = 'filament.forms.components.design-editor';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(QrDesignOptions::defaultDesign());

        $this->formatStateUsing(fn (mixed $state): array => is_array($state) && ($state['version'] ?? null) === 1
            ? $state
            : QrDesignOptions::defaultDesign());

        $this->rule(new ValidQrDesign);
    }

    /**
     * What the code encodes right now, for the first paint.
     */
    public function getEncodedContent(): string
    {
        $page = $this->getLivewire();

        return method_exists($page, 'encodedContent') ? $page->encodedContent() : '';
    }
}
