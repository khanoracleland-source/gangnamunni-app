<?php

namespace Modules\Plugins\Models;

use Illuminate\Database\Eloquent\Model;

class Plugins extends Model
{
    protected $table = 'plugins';
    protected $guarded = ['id'];
}
