<?php

namespace App\Modules\Task\Model;

use Illuminate\Database\Eloquent\Model;

class TaskPublishingModel extends Model
{
    protected $table = 'task_publishing';
    public $timestamps=false;
    protected $fillable = [
        'id',
        'uid',
        'cate_id',
        'task_id',
        'demand_number',
        'title',
        'content',
        'nickname',
        'mobile',
        'status',
        'created_at',
        'updated_at',
        'auth_time',
        'reason',
        'reasoname'
    ];


}
