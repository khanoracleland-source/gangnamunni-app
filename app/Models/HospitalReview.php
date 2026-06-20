<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HospitalReview extends Model
{
    public $table = 'hospital_reviews';

    protected $fillable = [
        'hospital_id', 'doctor_id', 'event_id', 'title',
        'user_name', 'user_avatar', 'rating', 'content',
        'editor_content', 'editor_type', 'treatments',
        'images', 'like_count', 'comment_count',
        'is_verified', 'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function gangnamunniHospital()
    {
        return $this->belongsTo(GangnamunniHospital::class, 'hospital_id');
    }

    public function reviewImages()
    {
        return $this->hasMany(ReviewImage::class, 'review_id')->orderBy('image_order');
    }

    public function reviewComments()
    {
        return $this->hasMany(ReviewComment::class, 'review_id')
            ->where('is_active', 1)
            ->orderBy('created_at', 'asc');
    }

    public function reviewReports()
    {
        return $this->hasMany(ReviewReport::class, 'review_id');
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = tenant('id') ?? null;
        if (!$tenantId) {
            return $query->whereRaw('1=0');
        }

        $hospitalIds = DB::table('gangnamunni_hospitals')
            ->where('hms_hospital_id', $tenantId)
            ->pluck('id');

        return $query->whereIn('hospital_id', $hospitalIds);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', 0);
    }
}
