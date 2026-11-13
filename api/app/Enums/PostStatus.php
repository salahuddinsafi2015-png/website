<?php

namespace App\Enums;

/**
 * Editorial status of a post, mirroring WordPress post statuses.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'publish';
    case Private = 'private';
    case Future = 'future';
    case Trash = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending review',
            self::Published => 'Published',
            self::Private => 'Private',
            self::Future => 'Scheduled',
            self::Trash => 'Trash',
        };
    }

    /**
     * Statuses that may be exposed on the public site.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
