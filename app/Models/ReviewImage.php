<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    public $table = 'review_images';
    public $timestamps = false;

    protected $fillable = [
        'review_id', 'image_url', 'thumbnail_url',
        'image_order', 'file_size', 'original_name',
    ];

    public function review()
    {
        return $this->belongsTo(HospitalReview::class, 'review_id');
    }
}
