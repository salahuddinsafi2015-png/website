<?php

namespace App\Enums;

/**
 * Account status. Suspended accounts cannot authenticate.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
