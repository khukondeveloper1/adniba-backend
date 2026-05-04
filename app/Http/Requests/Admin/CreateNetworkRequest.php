<?php

namespace App\Http\Requests\Admin;

use App\Models\AdNetwork;
use Illuminate\Foundation\Http\FormRequest;

class CreateNetworkRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'in:' . implode(',', AdNetwork::SUPPORTED)],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
