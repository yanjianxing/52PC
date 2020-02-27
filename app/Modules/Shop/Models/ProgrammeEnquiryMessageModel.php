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

class ProgrammeEnquiryMessageModel extends Model
{
    protected $table = 'programme_enquiry_message';
    //
    public $timestamps = false;
    protected $fillable = [
        'id',
        'uid',
        'nickname',
        'programme_id',
        'consult_type',
        'consultant_id',
        'consultant_name',
        'consultant_mobile',
        'created_at',
        'type',
        'pay_type',
    ];


}

