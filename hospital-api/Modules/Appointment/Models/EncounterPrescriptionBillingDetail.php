<?php

namespace Modules\Appointment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Appointment\Database\factories\EncounterPrescriptionBillingDetailFactory;

class EncounterPrescriptionBillingDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['encounter_id', 'exclusive_tax', 'exclusive_tax_amount', 'total_amount'];
    protected $casts = [
        'exclusive_tax' => 'array',
        'exclusive_tax_amount' => 'double',
        'total_amount' => 'double',
    ];

    protected static function newFactory(): EncounterPrescriptionBillingDetailFactory
    {
        //return EncounterPrescriptionBillingDetailFactory::new();
    }


    public function encounter()
    {
        return $this->belongsTo(PatientEncounter::class, 'encounter_id', 'id');
    }

}
