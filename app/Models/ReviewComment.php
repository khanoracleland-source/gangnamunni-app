<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewComment extends Model
{
    public $table = 'review_comments';

    protected $fillable = [
        'review_id', 'parent_id', 'user_id', 'user_name',
        'content', 'like_count', 'is_active',
    ];

    public function review()
    {
        return $this->belongsTo(HospitalReview::class, 'review_id');
    }

    public function replies()
    {
        return $this->hasMany(ReviewComment::class, 'parent_id');
    }
}
