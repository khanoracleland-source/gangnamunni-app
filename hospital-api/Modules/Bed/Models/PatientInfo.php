<?php

namespace Modules\Bed\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PatientInfo extends Model
{
    protected $table = 'patient_info';

    protected $fillable = [
        'patient_id',
        'weight',
        'height',
        'blood_pressure',
        'heart_rate',
        'blood_group',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
