<?php

namespace App\Http\Requests;

use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function isUpdate(): bool
    {
        return $this->isMethod('PATCH') || $this->isMethod('PUT');
    }

    /**
     * The type decides which table supplies the link target.
     */
    public function linkableType(): ?string
    {
        return match ($this->input('type')) {
            'post', 'page' => \App\Models\Post::class,
            'category' => \App\Models\Term::class,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $partial = $this->isUpdate();
        $type = (string) $this->input('type');
        $requiresLinkable = in_array($type, ['post', 'page', 'category'], true);

        $rules = [
            'label' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'type' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::in(['custom', 'post', 'page', 'category']),
            ],
            // "sometimes" and a conditional "required" cannot be combined:
            // sometimes short-circuits validation when the field is absent.
            'url' => $type === 'custom'
                ? ['required', 'string', 'max:2048']
                : ['sometimes', 'nullable', 'string', 'max:2048'],
            'target' => ['sometimes', 'string', Rule::in(['_self', '_blank'])],
            'rel' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('menu_items', 'id')],
        ];

        if ($requiresLinkable) {
            $rules['linkable_id'] = [
                Rule::requiredIf(fn (): bool => $requiresLinkable),
                'integer',
                $type === 'category'
                    ? Rule::exists('terms', 'id')->where('taxonomy', 'category')
                    : Rule::exists('posts', 'id')->where('type', $type),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'A custom link needs a URL.',
            'linkable_id.required' => 'Choose the content this item should link to.',
            'linkable_id.exists' => 'The selected content could not be found.',
        ];
    }

    public function existingItem(): ?MenuItem
    {
        return $this->route('menuItem');
    }
}
