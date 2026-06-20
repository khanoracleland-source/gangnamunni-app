<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReport extends Model
{
    public $table = 'review_reports';
    public $timestamps = false;

    protected $fillable = [
        'review_id', 'reporter_id', 'reason',
        'description', 'status',
    ];

    public function review()
    {
        return $this->belongsTo(HospitalReview::class, 'review_id');
    }
}
