<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOneModel extends Model
{
    protected $table = 'shop_one';

    public $timestamps = false;

    protected $fillable = [
        'id','uid', 'logo_pic','nav_pic','nav_open','about_us','about_pic1','about_pic2','consult','created_at','update_at','is_open'
    ];

  


}

