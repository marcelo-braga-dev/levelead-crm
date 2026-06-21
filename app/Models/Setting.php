<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parâmetros de negócio configuráveis sem deploy (limiares de compliance/SLA/scoring/
 * arquivamento — ver PLANO_IMPLEMENTACAO.md "Hardening pós-MVP"). Chave/valor simples,
 * sem Admin UI no MVP (mesmo precedente de `lead_distribution_rules`/`lead_scoring_rules`:
 * editado via tinker/seeder). `value` é sempre armazenado como JSON para preservar o tipo
 * (int/float/bool/string) na leitura.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::cacheKey($key), function () use ($key, $default) {
            $setting = self::query()->where('key', $key)->first();

            return $setting === null ? $default : json_decode($setting->value, true);
        });
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);

        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
