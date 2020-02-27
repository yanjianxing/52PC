<?php

namespace App\Modules\Shop\Models;

use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Task\Model\ServiceModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoodsFollowModel extends Model
{
    //
    protected $table = 'goods_follow';

    protected $primaryKey = 'id';

    protected $fillable = [
        'goods_id',
        'manager_name',
        'time',
        'desc',
        'created_at',
        'updated_at',
    ];

}
