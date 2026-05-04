<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateAppRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:100'],
            'package_name'      => ['required', 'string', 'max:100', 'unique:apps,package_name'],
            'app_logo'          => ['nullable', 'string'],
            'status'            => ['sometimes', 'in:active,inactive'],
            'app_status'        => ['sometimes', 'boolean'],
            'global_ad_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
