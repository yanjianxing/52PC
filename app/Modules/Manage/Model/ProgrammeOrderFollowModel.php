<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class ProgrammeOrderFollowModel extends Model
{
    //
    protected $table = 'programme_order_follow';
    protected $primaryKey = 'id';


    protected $fillable = [
        'order_id',
        'manager_name',
        'time',
        'desc',
        'type',
        'created_at',
        'updated_at',

    ];

}
