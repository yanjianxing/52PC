<?php

namespace App\Modules\Task\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskAgentModel extends Model
{
    protected $table = 'task_agent';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = [
		'id',
		'task_id',
		'plates_num',
		'plates_num_name',
		'pieces_num',
		'length',
		'width',
		'veneers_num',
		'plate_thickness',
		'plate_thickness_name',
		'copper_thickness',
		'copper_thickness_name',
		'spray_plating',
		'spray_plating_name',
		'soldering_color',
		'soldering_color_name',
		'character_color',
		'character_color_name',
		'is_connect',
		'delivery_cycle',
		'delivery_cycle_name',
	];

}
