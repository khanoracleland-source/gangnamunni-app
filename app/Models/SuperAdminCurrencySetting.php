<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * App\Models\SuperAdminCurrencySetting
 *
 * @property int $id
 * @property string $currency_name
 * @property string $currency_code
 * @property string $currency_icon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting query()
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereCurrencyIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereCurrencyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SuperAdminCurrencySetting whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SuperAdminCurrencySetting extends Model
{
    use HasFactory;

    protected $table = 'super_admin_currency_settings';

    public const CACHE_KEY = 'super_admin_currency_settings_all';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['currency_name', 'currency_code', 'currency_icon'];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'currency_name' => 'required|max:25|unique:super_admin_currency_settings,currency_name',
        'currency_icon' => 'required',
        'currency_code' => 'required|min:3|max:3|unique:super_admin_currency_settings,currency_code',
    ];

    protected $casts = [
        'currency_name' => 'string',
        'currency_icon' => 'string',
        'currency_code' => 'string',
    ];

    protected static function booted()
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
