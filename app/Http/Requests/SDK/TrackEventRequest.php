<?php

namespace App\Http\Requests\SDK;

use App\Models\AdEvent;
use App\Models\AdNetwork;
use App\Models\AdUnit;
use Illuminate\Foundation\Http\FormRequest;

class TrackEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network'    => ['required', 'string', 'in:' . implode(',', AdNetwork::SUPPORTED)],
            'ad_type'    => ['required', 'string', 'in:' . implode(',', AdUnit::AD_TYPES)],
            'placement'  => ['required', 'string', 'max:50'],
            'event_type' => ['required', 'string', 'in:' . implode(',', AdEvent::EVENT_TYPES)],
        ];
    }
}
