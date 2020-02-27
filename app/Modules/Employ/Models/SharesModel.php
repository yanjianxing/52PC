<?php

namespace App\Modules\Employ\Models;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SharesModel extends Model
{
    protected $table = 'shares';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'uid',
        'share_id',
        'type',
        'created_at',
    ];
}
