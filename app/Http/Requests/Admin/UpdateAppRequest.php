<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $appId = $this->route('id');

        return [
            'name'              => ['sometimes', 'string', 'max:100'],
            'package_name'      => ['sometimes', 'string', 'max:100', "unique:apps,package_name,{$appId}"],
            'app_logo'          => ['nullable', 'string'],
            'status'            => ['sometimes', 'in:active,inactive'],
            'app_status'        => ['sometimes', 'boolean'],
            'global_ad_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
