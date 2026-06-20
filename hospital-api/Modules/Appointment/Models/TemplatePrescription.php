<?php

namespace Modules\Appointment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Appointment\Database\factories\TemplatePrescriptionFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TemplatePrescription extends BaseModel
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
    protected $table = 'template_prescription';

    protected $fillable = ['template_id','medicine_id','name','frequency','quantity','duration','instruction'];

    protected static function newFactory(): TemplatePrescriptionFactory
    {
        //return TemplatePrescriptionFactory::new();
    }


    public function medicine()
    {
        try {
            if (class_exists('Modules\Pharma\Models\Medicine')) {
                return $this->belongsTo('Modules\Pharma\Models\Medicine', 'medicine_id');
            }
        } catch (\Exception $e) {
            // Class doesn't exist, return empty relationship
        }
        // Return empty relationship when Medicine class doesn't exist
        return $this->belongsTo(\Modules\Appointment\Models\TemplatePrescription::class, 'id', 'id')->whereRaw('1 = 0');
    }

}
