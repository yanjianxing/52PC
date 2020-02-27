<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;

class UserToolModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $table = 'user_tool';

    public $timestamps = false;

    protected $fillable = [
        'id','order_num', 'tool_id','uid','price','start_time','end_time','created_at','status','pay_status'
    ];




}