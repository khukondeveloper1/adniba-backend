<?php

namespace App\Http\Requests\Admin;

use App\Models\AdUnit;
use Illuminate\Foundation\Http\FormRequest;

class UpsertAdSettingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ad_type'          => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'        => ['required', 'string', 'max:50'],
            'fallback_enabled' => ['required', 'boolean'],
            // network_id is required only when fallback_enabled = false
            'network_id'       => [
                'nullable',
                'integer',
                'exists:ad_networks,id',
                function ($attribute, $value, $fail) {
                    if (!$this->input('fallback_enabled') && empty($value)) {
                        $fail('network_id is required when fallback_enabled is false (force mode).');
                    }
                },
            ],
        ];
    }
}
