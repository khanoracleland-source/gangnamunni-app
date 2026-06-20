<?php

namespace Modules\Bed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Bed\Models\BedMaster;
use Modules\Bed\Models\PatientInfo;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointment\Models\PatientEncounter;
use Modules\Clinic\Models\Receptionist;

class BedAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'clinic_admin_id',
        'clinic_id',
        'bed_type_id',
        'bed_master_id',
        'assign_date',
        'discharge_date',
        'status',
        'description',
        'temperature',
        'symptoms',
        'notes',
        'charge',
        'per_bed_charge',
        'bed_payment_status',
        'is_bad_payment',
    ];

    protected $casts = [
        'assign_date' => 'datetime',
        'discharge_date' => 'datetime',
        'status' => 'boolean',
        'charge' => 'double',
        'per_bed_charge' => 'double',
        'payment_status' => 'boolean'
    ];

    public function patientEncounter()
    {
        return $this->belongsTo(PatientEncounter::class, 'encounter_id', 'id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function bedMaster(): BelongsTo
    {
        return $this->belongsTo(BedMaster::class, 'bed_master_id');
    }

    public function patientInfo(): BelongsTo
    {
        return $this->belongsTo(PatientInfo::class, 'patient_id', 'patient_id');
    }

    public function clinicAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinic_admin_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(\Modules\Clinic\Models\Clinics::class, 'clinic_id');
    }

    public function bedType(): BelongsTo
    {
        return $this->belongsTo(\Modules\Bed\Models\BedType::class, 'bed_type_id');
    }

    /**
     * Scope for active allocations
     */
    public function scopeActive($query)
    {
        return $query->where('status', true)
            ->where(function($q) {
                $q->whereNull('discharge_date')
                    ->orWhere('discharge_date', '>', now());
            });
    }

    /**
     * Scope for inactive allocations
     */
    public function scopeInactive($query)
    {
        return $query->where('status', false)
            ->orWhere(function($q) {
                $q->whereNotNull('discharge_date')
                    ->where('discharge_date', '<=', now());
            });
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
                    $query = $query->with('patientEncounter')->whereHas('patientEncounter', function ($query) use ($user_id) {
                        $query->where('doctor_id', $user_id);
                    });
                } else {
                    $query = $query->with('patientEncounter')->whereHas('patientEncounter', function ($query) use ($user_id) {
                        $query->where('doctor_id', $user_id);
                    });
                }

                return $query;
            }

            if (auth()->user()->hasRole('receptionist')) {

                $Receptionist = Receptionist::where('receptionist_id', $user_id)->first();
                $clinic_id = $Receptionist->clinic_id;
                if (multiVendor() == "0") {

                    $query = $query->with('patientEncounter')->whereHas('patientEncounter', function ($query) use ($clinic_id) {
                        $query->where('clinic_id', $clinic_id);
                    });

                } else {

                    $query = $query->with('patientEncounter')->whereHas('patientEncounter', function ($query) use ($clinic_id) {
                        $query->where('clinic_id', $clinic_id);
                    });

                }

                return $query;

            }

        }
       
        return $query;
    }
}
