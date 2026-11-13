<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    /** @use HasFactory<\Database\Factories\BackupFactory> */
    use HasFactory;

    public const KIND_DATABASE = 'database';
    public const KIND_MEDIA = 'media';
    public const KIND_FULL = 'full';

    /** @var array<int, string> */
    public const KINDS = [self::KIND_DATABASE, self::KIND_MEDIA, self::KIND_FULL];

    protected $fillable = [
        'user_id',
        'kind',
        'disk',
        'path',
        'filename',
        'size',
        'checksum',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isArchive(): bool
    {
        return $this->kind !== self::KIND_DATABASE;
    }
}
