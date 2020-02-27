<?php

namespace App\Modules\Shop\Models;

use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\SkillTagsModel;
use App\Modules\User\Model\TagsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ShopViewLogModel extends Model
{
    protected $table = 'shop_view_log';
	protected $timestamp=false;
    //
    protected $fillable = [
        'id',
        'user_id',
        'shop_id',
        'uid',
        'create_at',
    ];

   

}

