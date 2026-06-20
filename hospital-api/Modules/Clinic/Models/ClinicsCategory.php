<?php

namespace Modules\Clinic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Clinic\Database\factories\ClinicsCategoryFactory;
use App\Trait\CustomFieldsTrait;
use App\Models\BaseModel;
use Auth;
use App\Models\Traits\HasSlug;


class ClinicsCategory extends BaseModel
{
    use HasFactory;
    use CustomFieldsTrait;
    use HasSlug;


    const CUSTOM_FIELD_MODEL = 'Modules\Clinic\Models\ClinicsCategory';

    protected $table = 'clinics_categories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['slug','name','parent_id','description','vendor_id','featured','status'];

    protected $appends = ['file_url'];

    protected $casts = [
        'client_id' => 'integer',
        'parent_id' => 'integer',
        'status' => 'integer',
        'featured' => 'integer',
    ];

    protected static function newFactory(): ClinicsCategoryFactory
    {
        //return ClinicsCategoryFactory::new();
    }


    public function mainCategory()
    {
        return $this->belongsTo(ClinicsCategory::class, 'parent_id','id');
    }


    public function subCategories()
    {
        return $this->hasMany(ClinicsCategory::class, 'parent_id');
    }

    protected static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($table) {
            //
        });

        static::saving(function ($table) {
            //
        });

        static::updating(function ($table) {
            //
        });
    }

    protected function getFileUrlAttribute()
    {
        $media = $this->getFirstMediaUrl('file_url');

        return isset($media) && ! empty($media) ? $media : default_file_url();
    }


    // public function services()
    // {
    //     return $this->hasMany(Service::class, 'category_id');
    // }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeSetVendor($query)
    {
        $vendorId = Auth::id();
        return $query->where('vendor_id', $vendorId);
    }

}
