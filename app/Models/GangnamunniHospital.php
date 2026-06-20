<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GangnamunniHospital extends Model
{
    public $table = 'gangnamunni_hospitals';

    protected $fillable = [
        'name', 'description', 'address', 'phone',
        'email', 'hms_hospital_id',
    ];

    public function reviews()
    {
        return $this->hasMany(HospitalReview::class, 'hospital_id');
    }

    public static function findByTenantId($tenantId)
    {
        return static::where('hms_hospital_id', $tenantId)->first();
    }
}
