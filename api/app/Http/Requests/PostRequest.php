<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and updating posts.
 */
abstract class PostRequest extends FormRequest
{
    /**
     * Whether the request targets an existing post.
     */
    abstract protected function isUpdate(): bool;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function postRules(): array
    {
        $partial = $this->isUpdate();

        return [
            'title' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'alpha_dash'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'blocks' => ['sometimes', 'nullable', 'array', 'max:500'],
            // The format is derived server-side (blocks render to HTML); this
            // rule accepts what the client may send, not what it controls.
            'content_format' => ['sometimes', 'string', Rule::in(['html', 'blocks'])],
            'type' => [
                'sometimes',
                'string',
                Rule::in(array_keys((array) config('cms.post_types', []))),
            ],
            'status' => ['sometimes', 'string', Rule::in(PostStatus::values())],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'comment_status' => ['sometimes', 'string', Rule::in(['open', 'closed'])],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'menu_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_sticky' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('posts', 'id')],
            'featured_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media', 'id')],
            'og_image_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media', 'id')],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'term_ids' => ['sometimes', 'array'],
            'term_ids.*' => ['integer', Rule::exists('terms', 'id')],
            'revision_note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->postRules();

        // On create, publishing states demand a title. On update the existing
        // title is kept when the field is omitted, so no requirement applies.
        if (! $this->isUpdate()) {
            $rules['title'][] = Rule::requiredIf(fn (): bool => in_array(
                $this->input('status'),
                [PostStatus::Published->value, PostStatus::Future->value, PostStatus::Private->value],
                true,
            ));
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required to publish, schedule or privatise a post.',
            'status.in' => 'The selected status is not a valid post status.',
            'type.in' => 'The selected post type is not registered.',
            'slug.alpha_dash' => 'The slug may only contain letters, numbers, dashes and underscores.',
        ];
    }
}
