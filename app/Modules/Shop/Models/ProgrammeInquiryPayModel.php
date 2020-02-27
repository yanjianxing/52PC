<?php

namespace App\Modules\Shop\Models;

use App\Modules\Advertisement\Model\RecommendModel;
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

class ProgrammeInquiryPayModel extends Model
{
    //
    protected $table = 'programme_inquiry_pay';

    protected $primaryKey = 'id';
	
    public $timestamps =false;
	
    protected $fillable = [
        'id',
        'order_num',
        'programme_id',
        'uid',
        'price',
        'payment_at',
        'created_at',
        'status',
        'type',
    ];

    /**
     * 方案
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function goods()
    {
        return $this->hasOne('App\Modules\Shop\Models\GoodsModel','id','programme_id')->select('title','id');
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid')->select('id','name');
    }


}
