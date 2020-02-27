<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PromoteFreegrantUserlistModel extends Model
{
    //
    protected $table = 'promote_freegrant_userlist';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','uid','action','prize','type','order_id','created_at'
    ];

    public $timestamps = false;


}
