<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeoRelatedModel extends Model
{
    //
    protected $table = 'seo_related';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','seo_id','related_id','type'
    ];

    public $timestamps = false;


}
