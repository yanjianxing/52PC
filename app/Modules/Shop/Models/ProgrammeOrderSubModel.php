<?php

namespace App\Modules\Shop\Models;

use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\SkillTagsModel;
use App\Modules\User\Model\TagsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProgrammeOrderSubModel extends Model
{
    protected $table = 'programme_order_sub';
    //
    public $timestamps = false;
    protected $fillable = [
        'id',
        'order_id',
        'programme_id',
        'number',
        'cash',
        'created_at',
    ];



}

