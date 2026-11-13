<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
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
        $partial = $this->isMethod('PATCH') || $this->isMethod('PUT');
        $menuId = $this->route('menu')?->getKey();

        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('menus', 'slug')->ignore($menuId),
            ],
            'location' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(array_keys((array) config('cms.menu_locations', []))),
                Rule::unique('menus', 'location')->ignore($menuId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.unique' => 'Another menu already occupies that location.',
        ];
    }
}
