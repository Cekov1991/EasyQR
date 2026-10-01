<?php

namespace App\Http\Requests;

use App\Rules\SitePage;
use App\Rules\ValidQrUrl;
use App\Support\LandingPages;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A request for a free static code, from whichever page embeds the generator.
 *
 * The URL is encoded and never kept. The page is the one fact that reaches
 * the `static_qr_generated` count, and only as a label from a closed set.
 */
class GenerateInstantQrRequest extends FormRequest
{
    /**
     * Public: the generator is for people without an account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new ValidQrUrl],
            'page' => ['nullable', new SitePage],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Please enter a URL.',
            'url.max' => 'That link is too long to fit in a QR code.',
        ];
    }

    /**
     * Where the code was generated. A missing label means the homepage, so a
     * homepage script cached from before the label existed keeps working.
     */
    public function page(): string
    {
        return LandingPages::pageLabelOrHome($this->validated('page'));
    }
}
