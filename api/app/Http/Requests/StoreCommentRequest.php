<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Commenting is open to guests, so no authorization gate here; the
     * controller verifies that the target post is published and open.
     */
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
            'author_name' => ['required', 'string', 'max:120'],
            'author_email' => ['required', 'string', 'email', 'max:255'],
            'author_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'content' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:comments,id'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
