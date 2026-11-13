<?php

namespace App\Enums;

/**
 * Built-in post types. Additional types can be registered in config/cms.php.
 */
enum PostType: string
{
    case Post = 'post';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post',
            self::Page => 'Page',
        };
    }

    /**
     * Whether this type is organised with categories and tags.
     */
    public function usesTaxonomies(): bool
    {
        return $this === self::Post;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
