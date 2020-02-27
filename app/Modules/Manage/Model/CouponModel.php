<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CouponModel extends Model
{
    //
    protected $table = 'coupon';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','name','store_num','price','full_price','start_time','end_time','uid','limit','status','created_at','is_grant','type'
    ];

    public $timestamps = false;


}
