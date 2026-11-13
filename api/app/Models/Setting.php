<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'type',
        'group',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget("cms.setting.{$setting->name}"));
        static::deleted(fn (Setting $setting) => Cache::forget("cms.setting.{$setting->name}"));
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            "cms.setting.{$name}",
            fn () => static::query()->where('name', $name)->first()?->typedValue(),
        );

        return $value ?? $default;
    }

    public static function set(string $name, mixed $value, array $attributes = []): self
    {
        $setting = static::query()->firstOrNew(['name' => $name]);
        $setting->fill($attributes);
        $setting->value = is_array($value) || is_bool($value) || is_int($value) || is_float($value)
            ? json_encode($value)
            : (string) $value;
        $setting->type = $setting->type ?: static::detectType($value);
        $setting->save();

        return $setting;
    }

    /**
     * Decode the stored value according to its declared type.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    public static function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    /**
     * Public settings exposed to the frontend.
     *
     * @return array<string, mixed>
     */
    public static function publicValues(): array
    {
        return static::query()
            ->where('is_public', true)
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->name => $setting->typedValue()])
            ->all();
    }
}
