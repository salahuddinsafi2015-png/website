<?php

namespace App\Enums;

/**
 * Built-in taxonomies. Custom taxonomies can be registered in config/cms.php.
 */
enum TermTaxonomy: string
{
    case Category = 'category';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category',
            self::Tag => 'Tag',
        };
    }

    /**
     * Categories are hierarchical; tags are flat.
     */
    public function isHierarchical(): bool
    {
        return $this === self::Category;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
