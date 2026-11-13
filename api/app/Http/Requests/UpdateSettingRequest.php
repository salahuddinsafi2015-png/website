<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['sometimes', 'string', 'in:string,integer,boolean,json,array'],
            'settings.*.group' => ['sometimes', 'string', 'max:60'],
            'settings.*.is_public' => ['sometimes', 'boolean'],
        ];
    }
}
