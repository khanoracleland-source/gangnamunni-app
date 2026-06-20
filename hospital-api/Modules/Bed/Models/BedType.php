<?php

namespace Modules\Bed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BedType extends Model
{
    use HasFactory;

    protected $table = 'bed_type';

    protected $fillable = [
        'type',
        'description',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    /**
     * Get all beds of this type
     */
    public function beds()
    {
        return $this->hasMany(BedMaster::class, 'bed_type_id');
    }

    /**
     * Get active beds of this type
     */
    public function activeBeds()
    {
        return $this->beds()->where('status', true);
    }

    /**
     * Get available beds of this type (active and not under maintenance)
     */
    public function availableBeds()
    {
        return $this->beds()
            ->where('status', true)
            ->where('is_under_maintenance', false)
            ->whereDoesntHave('currentAllocation', function($query) {
                $query->where('status', true)
                    ->where(function($q) {
                        $q->whereNull('discharge_date')
                          ->orWhere('discharge_date', '>', now());
                    });
            });
    }

    /**
     * Get occupied beds of this type
     */
    public function occupiedBeds()
    {
        return $this->beds()
            ->where('status', true)
            ->whereHas('currentAllocation', function($query) {
                $query->where('status', true)
                    ->where(function($q) {
                        $q->whereNull('discharge_date')
                          ->orWhere('discharge_date', '>', now());
                    });
            });
    }

    /**
     * Get maintenance beds of this type
     */
    public function maintenanceBeds()
    {
        return $this->beds()->where('is_under_maintenance', true);
    }

    /**
     * Get unavailable beds of this type
     */
    public function unavailableBeds()
    {
        return $this->beds()->where('status', false);
    }

    /**
     * Scope for active bed types
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope for inactive bed types
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

     public function author(){
        return $this->belongsTo('App\Models\User','author_id','id')->withTrashed();
    }
    public function bedmaster(){
        return $this->hasMany('Modules\Bed\Models\BedMaster','bed_type_id','id');
    }
}
