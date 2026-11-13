<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
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
        $taxonomies = array_keys((array) config('cms.taxonomies', []));
        $partial = $this->isMethod('PATCH') || $this->isMethod('PUT');
        $termId = $this->route('term')?->getKey();

        return [
            'taxonomy' => [$partial ? 'sometimes' : 'required', 'string', Rule::in($taxonomies)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:200'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('terms', 'id')->when($termId, fn ($rule) => $rule->whereNot('id', $termId)),
            ],
            'menu_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
