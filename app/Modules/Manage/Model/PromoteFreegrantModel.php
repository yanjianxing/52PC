<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PromoteFreegrantModel extends Model
{
    //
    protected $table = 'promote_freegrant';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','action','prize','prize_name','prize_type','is_open','created_at','startnum','endnum','selstart_time','selend_time','send_time'
    ];

    public $timestamps = false;


}
