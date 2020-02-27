<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/10/27
 * Time: 13:52
 */
namespace App\Modules\Vipshop\Models;

use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;

class InterviewModel extends Model
{
    //
    protected $table = 'interview';

    protected $primaryKey = 'id';

    protected $fillable = [

        'title', 'uid', 'username', 'shop_id', 'shop_name', 'shop_cover', 'desc','list','created_at','updated_at','view_count'

    ];

    /**
     * 获取访谈列表或筛选后的访谈列表
     * @param $data  筛选条件（可传可不传）
     * @return array
     */
    static function interviewList($data){
        $interviewList = InterviewModel::select('*');
        if(isset($data['username'])){
            $interviewList = $interviewList->where('username','like','%'.$data['username'].'%');
        }
        if(isset($data['shop_name'])){
            $interviewList = $interviewList->where('shop_name','like','%'.$data['shop_name'].'%');
        }
        $interviewList = $interviewList->orderBy('list','asc')->orderBy('created_at','desc')->paginate(10);
        return $interviewList;
    }


    /**
     * 删除访谈记录
     * @param 访谈记录id
     * @return data
     */
    static function deleteInterview($id){
        $interviewInfo = InterviewModel::find(intval($id));
        if(empty($interviewInfo)){
            return 2;
        }
        $res = $interviewInfo->delete();
        return $res?1:0;                  //1代表删除成功    0代表删除失败
    }


    /**
     * 获取可访谈的店铺信息
     * @return array $data
     */
    static function interviewShop(){
        $shopInfo = ShopPackageModel::join('shop','shop_package.shop_id','=','shop.id')
            ->where('shop_package.status',0)
            ->where('shop.status',1)
            ->select('shop.id','shop.shop_name')
            ->groupBy('shop_package.shop_id')
            ->get()->toArray();
        return $shopInfo;
    }


    /**
     * 根据店铺id获取店铺信息及用户信息
     * @param 店铺id
     * @return mixed
     */
    static function shopInfo($id){
        $shopUser = [];
        $shopId = intval($id);
        $shopInfo = ShopModel::where(['id' => $shopId])->first();
        if(empty($shopInfo)){
            return false;                           //传送参数错误
        }
        $userInfo = UserModel::where('id',$shopInfo->uid)->select('name')->first();
        if(empty($userInfo)){
            return false;                         //传送参数错误
        }
        //$shopUser['shop_id'] = $shopId;
        $shopUser['shop_name'] = $shopInfo->shop_name;
        $shopUser['shop_cover'] = $shopInfo->shop_pic;
        $shopUser['uid'] = $shopInfo->uid;
        $shopUser['username'] = $userInfo->name;
        return $shopUser;
    }

    /**
     * 创建访谈信息
     * @param array $data
     * @return bool
     */
    static function addInterview(array $data){
        $interviewInfo = [
            'title' => $data['title'],
            'uid' => $data['uid'],
            'username' => $data['username'],
            'shop_id' => $data['shop_id'],
            'shop_name' => $data['shop_name'],
            'shop_cover' => $data['shop_cover'],
            'desc' => $data['desc'],
            'list' => $data['list'],
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        $res = InterviewModel::create($interviewInfo);
        return $res?true:false;                 //true代表创建成功     false代表创建失败

    }

    /**
     * 查询访谈信息
     * @param 访谈id
     * @return mixed
     */
    static function interviewDetail($id){
        $interviewInfo = InterviewModel::find(intval($id));
        if(empty($interviewInfo)){
            return false;
        }
        return $interviewInfo;
    }


    /**
     * 编辑访谈信息
     * @param array $data  访谈id
     * @return bool
     */
    static function updateInterview($id,array $data){
        $interviewInfo = [
            'title' => $data['title'],
            'uid' => $data['uid'],
            'username' => $data['username'],
            'shop_id' => $data['shop_id'],
            'shop_name' => $data['shop_name'],
            'shop_cover' => $data['shop_cover'],
            'desc' => $data['desc'],
            'list' => $data['list'],
            'updated_at' => date('Y-m-d H:i:s',time())
        ];
        $res = InterviewModel::where('id',intval($id))->update($interviewInfo);
        return $res?true:false;                 //true代表修改成功     false代表修改失败

    }

}
