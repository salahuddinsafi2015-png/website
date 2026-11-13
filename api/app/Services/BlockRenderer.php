<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates the structured block tree the editor submits and compiles it into
 * the sanitised HTML that posts.content stores.
 *
 * Rendering happens server-side, at write time, so the public site keeps one
 * rendering path (the existing HTML field) and no block markup is ever
 * executed anywhere else. Every dynamic value is escaped with e(); the only
 * block that emits unescaped markup is `html`, which still runs through the
 * same ContentSanitizer as classic editor content.
 */
class BlockRenderer
{
    private const MAX_BLOCKS = 500;

    /**
     * Allowed types come from config('cms.block_types'); the schemas below
     * define the per-type field rules used for validation.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const SCHEMAS = [
        'paragraph' => [
            'text' => ['required', 'string', 'max:10000'],
        ],
        'heading' => [
            'level' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'text' => ['required', 'string', 'max:500'],
        ],
        'image' => [
            'src' => [
                'required',
                'string',
                'max:2048',
                'regex:#^(?:/|https?://)[^<>"\'\r\n\t\x00-\x1f]{1,2047}$#',
            ],
            'alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ],
        'quote' => [
            'text' => ['required', 'string', 'max:10000'],
            'citation' => ['sometimes', 'nullable', 'string', 'max:500'],
        ],
        'list' => [
            'style' => ['sometimes', 'string', 'in:bulleted,numbered'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'string', 'max:2000'],
        ],
        'code' => [
            'language' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_+-]*$/'],
            'code' => ['required', 'string', 'max:50000'],
        ],
        'divider' => [],
        'html' => [
            'content' => ['required', 'string', 'max:100000'],
        ],
    ];

    /**
     * Fields must be trimmed so empty paragraphs and stray newlines do not
     * reach storage as supposed content.
     */
    private const TRIMMED_FIELDS = [
        'paragraph' => ['text'],
        'heading' => ['text'],
        'image' => ['src', 'alt', 'caption'],
        'quote' => ['text', 'citation'],
        'list' => ['items'],
        'code' => ['language', 'code'],
        'divider' => [],
        'html' => ['content'],
    ];

    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    /**
     * Validate a block tree and return its rendered HTML.
     *
     * @param  array<int, mixed>  $blocks
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    public function process(array $blocks): array
    {
        $validated = $this->validated($blocks);

        return [
            $validated,
            implode(
                "\n\n",
                array_map(fn (array $block): string => $this->renderBlock($block), $validated),
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validated(array $blocks): array
    {
        if (array_values($blocks) !== $blocks) {
            throw ValidationException::withMessages([
                'blocks' => ['The blocks payload must be a plain list.'],
            ]);
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            throw ValidationException::withMessages([
                'blocks' => ['A post may contain at most '.self::MAX_BLOCKS.' blocks.'],
            ]);
        }

        $allowedTypes = (array) config('cms.block_types', []);
        $values = array_values($blocks);

        return array_map(function ($block, int $index) use ($allowedTypes) {
            if (! is_array($block) || ! array_key_exists('type', $block) || ! is_string($block['type'])) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.type" => ['Every block must declare a type string.'],
                ]);
            }

            $type = $block['type'];

            if (! array_key_exists($type, self::SCHEMAS)) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.type" => ['The block type is not registered.'],
                ]);
            }

            if (! array_key_exists($type, $allowedTypes)) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.type" => ['The selected block type is not available on this site.'],
                ]);
            }

            $fields = self::SCHEMAS[$type];
            $allowedKeys = ['type', ...array_keys($fields)];

            try {
                $validated = Validator::validate(
                    array_intersect_key(
                        array_merge(['type' => $type], $block),
                        array_fill_keys($allowedKeys, null),
                    ),
                    ['type' => ['required', 'string', 'in:'.$type]] + $fields,
                );
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field) => [
                        "blocks.{$index}.{$field}" => $messages,
                    ])
                    ->all());
            }

            $clean = ['type' => $type];

            foreach ($fields as $field => $rule) {
                $value = $validated[$field] ?? null;

                if (in_array($field, self::TRIMMED_FIELDS[$type], true)) {
                    $value = $this->trimValue($value);
                }

                $clean[$field] = $value;
            }

            if ($type === 'heading') {
                $clean['level'] = (int) ($clean['level'] ?? 2);
            }

            if ($type === 'list') {
                $clean['style'] = $clean['style'] ?? 'bulleted';
            }

            $this->assertSameNotEmptyContent($type, $index, $clean);

            return $clean;
        }, $values, array_keys($values));
    }

    /**
     * A block that would render nothing is treated as invalid input rather
     * than a silent hole in the article.
     */
    private function assertSameNotEmptyContent(string $type, int $index, array $block): void
    {
        $empty = match ($type) {
            'paragraph', 'quote' => blank($block['text'] ?? null),
            'heading' => blank($block['text'] ?? null),
            'code' => blank($block['code'] ?? null),
            'html' => blank($block['content'] ?? null),
            'image' => blank($block['src'] ?? null),
            'list' => ($block['items'] ?? []) === [],
            'divider' => false,
            default => true,
        };

        if ($empty) {
            throw ValidationException::withMessages([
                "blocks.{$index}.{$type}" => ['That block would render nothing; give it content or remove it.'],
            ]);
        }
    }

    /**
     * @param  mixed|array<int,mixed>  $value
     * @return mixed
     */
    private function trimValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => is_string($item) ? trim($item) : $item, $value);
        }

        if (is_string($value)) {
            return $value === '' ? null : trim($value);
        }

        return $value;
    }

    private function renderBlock(array $block): string
    {
        return match ($block['type']) {
            'paragraph' => '<p>'.e($block['text']).'</p>',
            'heading' => '<h'.((int) $block['level']).'>'.e($block['text']).'</h'.((int) $block['level']).'>',
            'image' => $this->renderImage($block),
            'quote' => $this->renderQuote($block),
            'list' => $this->renderList($block),
            'code' => $this->renderCode($block),
            'divider' => '<hr>',
            'html' => (string) $this->sanitizer->clean((string) $block['content']),
        };
    }

    private function renderImage(array $block): string
    {
        $src = e($block['src']);
        $alt = e($block['alt'] ?? '');

        $figure = '<figure class="cms-block cms-block--image">'
            .'<img src="'.$src.'" alt="'.$alt.'" loading="lazy" decoding="async">';

        if (filled($block['caption'] ?? null)) {
            $figure .= '<figcaption>'.e($block['caption']).'</figcaption>';
        }

        return $figure.'</figure>';
    }

    private function renderQuote(array $block): string
    {
        $citation = $block['citation'] ?? null;

        return '<blockquote><p>'.e($block['text']).'</p>'.($citation !== null
            ? '<cite>'.e($citation).'</cite>'
            : '').'</blockquote>';
    }

    private function renderList(array $block): string
    {
        $tag = $block['style'] === 'numbered' ? 'ol' : 'ul';

        $items = implode('', array_map(fn (string $item): string => '<li>'.e($item).'</li>', $block['items']));

        return '<'.$tag.'>'.$items.'</'.$tag.'>';
    }

    private function renderCode(array $block): string
    {
        $language = $block['language'] ?? null;
        $class = filled($language) ? ' class="language-'.e($language).'"' : '';

        return '<pre><code'.$class.'>'.e($block['code']).'</code></pre>';
    }
}
