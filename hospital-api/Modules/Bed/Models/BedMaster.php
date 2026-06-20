<?php

namespace Modules\Bed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BedMaster extends Model
{
    use HasFactory;

    protected $table = 'bed_master';

    protected $fillable = [
        'bed',
        'bed_type_id',
        'charges',
        'capacity',
        'description',
        'status',
        'is_under_maintenance',
        'clinic_admin_id',
        'clinic_id',
    ];

    protected $casts = [
        'charges' => 'decimal:2',
        'capacity' => 'integer',
        'status' => 'boolean',
        'is_under_maintenance' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the bed type that owns the bed master.
     */
    public function bedType()
    {
        return $this->belongsTo(BedType::class, 'bed_type_id');
    }

    /**
     * Get the current allocation for the bed.
     */
    public function currentAllocation()
    {
        return $this->hasOne(BedAllocation::class, 'bed_master_id')
            ->where('status', true)
            ->whereNull('deleted_at')
            ->where(function($q) {
                $q->whereNull('discharge_date')
                  ->orWhere('discharge_date', '>=', now());
            });
    }

    /**
     * Get all allocations for the bed.
     */
    public function allocations()
    {
        return $this->hasMany(BedAllocation::class, 'bed_master_id');
    }

    /**
     * Scope for active bed masters
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope for inactive bed masters
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    /**
     * Get formatted charges
     */
    public function getFormattedChargesAttribute()
    {
        return \Currency::format($this->charges);
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return $this->status ? 'bg-success' : 'bg-danger';
    }

    /**
     * Get the bed's current status
     */
    public function getCurrentStatusAttribute()
    {
        // If current_status is already set as an attribute, use it (avoids overriding manually set values)
        if ($this->attributes['current_status'] ?? null) {
            return $this->attributes['current_status'];
        }
        
        // Check if bed is inactive (status = 0) or under maintenance - both should be unavailable
        if ($this->is_under_maintenance || !$this->status) {
            return 'maintenance';
        }
        // Check if bed has an active allocation (don't check allocation status, just if it exists with valid dates)
        if ($this->currentAllocation) {
            $dischargeDate = $this->currentAllocation->discharge_date;
            // If discharge_date is null or in the future, bed is occupied
            if (is_null($dischargeDate) || \Carbon\Carbon::parse($dischargeDate)->isFuture()) {
                return 'occupied';
            }
        }
        return 'available';
    }

    /**
     * Get the clinic admin (vendor) for the bed master.
     */
    public function clinicAdmin()
    {
        return $this->belongsTo(\App\Models\User::class, 'clinic_admin_id');
    }

    /**
     * Get the clinic for the bed master.
     */
    public function clinic()
    {
        return $this->belongsTo(\Modules\Clinic\Models\Clinics::class, 'clinic_id');
    }


    public function scopeSetRole($query, $user)
    {
        $user_id = $user->id ?? null;
        if($user_id != null){
            if (auth()->user()->hasRole(['admin', 'demo_admin'])) {
                return $query;
            }

            if ($user->hasRole('vendor')) {

                $query->where('clinic_admin_id', $user_id);
                return $query;
            }

            if (auth()->user()->hasRole('doctor')) {

                if (multiVendor() == 0) {
                    $query = $query->where('clinic_admin_id', $user_id);
                } else {
                    $query = $query->where('clinic_admin_id', $user_id);
                }

                return $query;
            }

            if (auth()->user()->hasRole('receptionist')) {

                if (multiVendor() == "0") {

                    $query = $query->where('clinic_admin_id', $user_id);

                } else {

                    $query = $query->where('clinic_admin_id', $user_id);

                }

                return $query;

            }

        }
       
        return $query;
    }
}