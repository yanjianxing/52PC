<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/9/20
 * Time: 10:41
 */
namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ShopFocusModel extends Model
{
    protected $table = 'shop_focus';

    public $timestamps = false;

    protected $fillable = [
        'id','uid', 'shop_id','created_at'
    ];

    /**
     * 根据店铺id查询店铺被收藏状态
     * @param $shop_id
     * @return string
     *
     */
    static function shopFocusStatus($shopId)
    {
        if(Auth::check()){
            $uid = Auth::id();
            $isFocus = ShopFocusModel::where(['uid' => $uid,'shop_id' =>$shopId])->first();
        }
        else{
            $isFocus = [];
        }
        return $isFocus;
    }


}