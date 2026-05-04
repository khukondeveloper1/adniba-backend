<?php

namespace App\Http\Requests\SDK;

use App\Models\AdUnit;
use Illuminate\Foundation\Http\FormRequest;

class AdConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorisation handled by middleware
    }

    public function rules(): array
    {
        return [
            'ad_type'   => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'ad_type.in' => 'ad_type must be one of: ' . implode(', ', AdUnit::AD_TYPES),
        ];
    }
}
