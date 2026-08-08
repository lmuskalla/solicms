<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'type', 'value'])]
class SiteConfig extends Model
{
    protected $table = 'site_config';

    public static function get(string $key, string $default = ''): string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }

    /**
     * All config as a flat key => value map, for handing to the frontend.
     */
    public static function allAsMap(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}
