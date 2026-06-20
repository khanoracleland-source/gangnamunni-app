<?php

namespace Modules\Appointment\Models;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Appointment\Database\factories\EncounterPrescriptionFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Appointment\Models\PatientEncounter;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;


class EncounterPrescription extends Model
{
    use HasFactory;
    use SoftDeletes,LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*']);
    }
    

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'encounter_prescription';
    
    protected $fillable = ['encounter_id','user_id','name','frequency','duration','instruction', 'medicine_id','quantity', 'inclusive_tax', 'inclusive_tax_amount', 'medicine_price', 'total_amount'];
    protected $casts = [
        'inclusive_tax' => 'array',
        'inclusive_tax_amount' => 'double',
        'total_amount' => 'double',
    ];
    protected static function newFactory(): EncounterPrescriptionFactory
    {
        //return EncounterPrescriptionFactory::new();
    }

    public function encounter()
    {
        return $this->belongsTo(PatientEncounter::class)->with('clinic','doctor');
    }


    public function medicine()
    {
        try {
            if (class_exists('Modules\Pharma\Models\Medicine')) {
                return $this->belongsTo('Modules\Pharma\Models\Medicine', 'medicine_id')->with('form');
            }
        } catch (\Exception $e) {
            // Class doesn't exist, return empty relationship
        }
        // Return empty relationship when Medicine class doesn't exist
        return $this->belongsTo(\Modules\Appointment\Models\EncounterPrescription::class, 'id', 'id')->whereRaw('1 = 0');
    }

    public function billingDetail()
    {
        return $this->hasOne(\Modules\Appointment\Models\EncounterPrescriptionBillingDetail::class, 'encounter_id', 'encounter_id');
    }
    
}
