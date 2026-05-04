<?php

namespace App\Http\Requests\Admin;

use App\Models\AdUnit;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdUnitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'network_id' => ['required', 'integer', 'exists:ad_networks,id'],
            'ad_type'    => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'  => ['required', 'string', 'max:50'],
            'unit_id'    => ['required', 'string', 'max:200'],
            'priority'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'enabled'    => ['sometimes', 'boolean'],
        ];
    }
}
