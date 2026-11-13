<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWidgetRequest extends FormRequest
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
        $type = (string) $this->input('type');

        return [
            'area' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::in(array_keys((array) config('cms.widget_areas', []))),
            ],
            'type' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::in(array_keys((array) config('cms.widget_types', []))),
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'content' => ['sometimes', 'nullable', 'string', 'max:20000'],
            // A menu widget must reference a menu; other types cannot.
            'menu_id' => $type === 'menu'
                ? ['required', 'integer', Rule::exists('menus', 'id')]
                : ['sometimes', 'nullable', 'integer', Rule::exists('menus', 'id')],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'menu_id.required' => 'A menu widget needs a menu to render.',
        ];
    }
}
