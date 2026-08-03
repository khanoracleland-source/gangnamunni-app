<?php

namespace App\Models;

use App\Traits\PopulateTenantID;
use Eloquent as Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Class Service
 *
 * @version February 25, 2020, 10:50 am UTC
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $quantity
 * @property float $rate
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @method static Builder|Service newModelQuery()
 * @method static Builder|Service newQuery()
 * @method static Builder|Service query()
 * @method static Builder|Service whereCreatedAt($value)
 * @method static Builder|Service whereDeletedAt($value)
 * @method static Builder|Service whereDescription($value)
 * @method static Builder|Service whereId($value)
 * @method static Builder|Service whereName($value)
 * @method static Builder|Service whereQuantity($value)
 * @method static Builder|Service whereRate($value)
 * @method static Builder|Service whereStatus($value)
 * @method static Builder|Service whereUpdatedAt($value)
 *
 * @mixin Model
 *
 * @property int $is_default
 *
 * @method static Builder|Service whereIsDefault($value)
 */
class Service extends Model
{
    use BelongsToTenant, PopulateTenantID;

    const STATUS_ALL = 2;

    const ACTIVE = 1;

    const INACTIVE = 0;

    const STATUS_ARR = [
        self::STATUS_ALL => 'All',
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Deactive',
    ];

    const FILTER_STATUS_ARRAY = [
        0 => 'All',
        1 => 'Active',
        2 => 'Deactive',
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'name' => 'required|string|max:160|is_unique:services,name',
        'description' => 'nullable|string|max:5000',
        'quantity' => 'required|integer|min:1|max:99999',
        'rate' => 'required|numeric|decimal:0,2|min:0|max:9999999999999.99',
        'status' => 'sometimes|boolean',
    ];

    public $table = 'services';

    public $fillable = [
        'name',
        'description',
        'quantity',
        'rate',
        'status',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'description' => 'string',
        'quantity' => 'integer',
        'rate' => 'double',
        'status' => 'integer',
    ];
}
