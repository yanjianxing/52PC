<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpecialModel extends Model
{
    //
    protected $table = 'special';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','title','logo','banner','introduction','news_id','created_at','status','view_times','edit_by','desc','is_open'
    ];

    public $timestamps = false;


}
