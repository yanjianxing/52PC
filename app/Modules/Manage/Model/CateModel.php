<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CateModel extends Model
{
    //
    protected $table = 'cate';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','name','pid','type','choose_num','pic','created_at','updated_at'
    ];

    public $timestamps = false;


}
