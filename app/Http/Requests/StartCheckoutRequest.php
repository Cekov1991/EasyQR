<?php

namespace App\Http\Requests;

use App\Enums\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A checkout must name the Plan the buyer is choosing.
 *
 * A request that names none is refused rather than given the default. The
 * Plan chosen here becomes the payment link that is charged, the Plan stored
 * on the Subscription row, and the length of the provisional grant when the
 * payment lands — a guess on the buyer's behalf could bill one period and
 * grant another. See docs/adr/0003.
 */
class StartCheckoutRequest extends FormRequest
{
    /**
     * Any signed-in user may start a checkout; the route's auth middleware is
     * the gate.
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
            'plan' => ['required', Rule::enum(Plan::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Please choose a plan.',
            'plan.Illuminate\Validation\Rules\Enum' => 'Please choose one of the listed plans.',
        ];
    }

    public function plan(): Plan
    {
        return Plan::from($this->validated('plan'));
    }
}
