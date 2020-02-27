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

class AnswerModel extends Model
{
    protected $table = 'answer';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'uid',
        'article_id',
        'content',
        'adopt',
        'cash',
        'time',
        'praisenum',
        'replyid',
        'reason'
    ];

    static public function CommentCreate($data)
    {
        $result = self::create($data);
        return $result?true:false;
    }
}
