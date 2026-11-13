<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
        /** @var array<int, string> $mimes */
        $mimes = (array) config('cms.media.mimes', []);
        /** @var array<int, string> $extensions */
        $extensions = (array) config('cms.media.extensions', []);

        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('cms.media.max_size_kb', 5120),
                'mimetypes:'.implode(',', $mimes),
                'extensions:'.implode(',', $extensions),
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
