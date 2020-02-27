<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/31
 * Time: 14:36
 */
namespace App\Modules\Advertisement\Model;

use App\Modules\Article\Model\ArticleModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Modules\Manage\Model\SpecialModel;
use App\Modules\Manage\Model\SpecialNewsModel;

class RecommendModel extends Model
{
    protected $table = 'recommend';
    protected $fillable =
        [   'id',
            'position_id',
            'type',
            'recommend_id',
            'recommend_type',
            'recommend_name',
            'recommend_pic',
            'url',
            'start_time',
            'end_time',
            'sort',
            'is_open',
            'created_at',
            'sort'
        ];
    public $timestamps = false;  //关闭自动更新时间戳

    /**
     * 根据推荐位获取推荐内容
     * @param $code
     * @return bool|mixed
     */
    static public function getRecommendByCode($code,$type,$data=[])
    {
        $position = RePositionModel::where('code',$code)->where('is_open',1)->first();
        if($position){
            $recommend = self::getRecommendInfo($position['id'],$type,$data);
            return $recommend;
        }else{
            return [];
        }

    }

    /**
     * 根据推荐位id和类型获取推荐位信息
     * @param int $recommendPositionId 推荐位位置id
     * @param string $recommendType 推荐位类型
     * @return mixed
     */
    static function getRecommendInfo($recommendPositionId,$recommendType='goods',$data)
    {
        $recommend = RecommendModel::where('recommend.position_id',$recommendPositionId);
        $recommend = $recommend->where('recommend.is_open',1)
            ->where(function($query){
                $query->where('recommend.end_time','0000-00-00 00:00:00')
                    ->orWhere('recommend.end_time','>',date('Y-m-d h:i:s',time()));
            })->orderBy('recommend.sort','ASC')->orderBy('recommend.created_at','DESC')->get()->toArray();
        $recommendId = array_pluck($recommend,'recommend_id');
        switch($recommendType){
            case 'goods' :
                $info = GoodsModel::whereIn('id',$recommendId);
                if((isset($data['shop_info']) && $data['shop_info'] != false)){
                    $info = $info->with('user');
                }
                if((isset($data['goods_field']) && $data['goods_field'] != false)){
                    $info = $info->with('field');
                }
                $info = $info->with('cover')->get()->toArray();
                break;
            case 'task' :
                $info = TaskModel::whereIn('task.id',$recommendId)->where('task.type_id',1);
                if(isset($data['field_id']) && !empty($data['field_id'])){
                    $info = $info->where('task.field_id',$data['field_id']);
                }
                $info = $info->with('province','city')->leftJoin('cate','cate.id','=','task.field_id')->select('task.*','cate.name as task_field_name')->get()->toArray();
                break;
            case 'service' :
                $info = UserModel::whereIn('id',$recommendId)->leftJoin('user_detail','user_detail.uid','=','user.id')->select('users.*','user_detail.avatar')->get()->toArray();

                break;
            case 'successcase' :
                $info = SuccessCaseModel::whereIn('id',$recommendId)->get()->toArray();
                break;
            case 'special' :
                $info = SpecialModel::whereIn('id',$recommendId)->get()->toArray();
                break;
            case 'shop' :
                $info = ShopModel::whereIn('id',$recommendId)
                    ->select('shop_pic', 'shop_name', 'shop_desc','id','uid','publish_task_num','receive_task_num','delivery_count','province','city','area')->with('province','city')->get()->toArray();
                if($info){
                    $uidArr = array_pluck($info,'uid');
                    $auth = [];
                    if((isset($data['auth']) && $data['auth'] != false) || !isset($data['auth'])){
                        $auth = ShopModel::getShopAuth($uidArr);
                    }
                    $goods = [];
                    if((isset($data['goods']) && $data['goods'] != false) || !isset($data['goods'])){
                        $goods = ShopModel::getGoodsByUid($uidArr);
                    }
                    foreach($info as $k => $v){
                        $info[$k]['auth'] = in_array($v['uid'],array_keys($auth)) ? $auth[$v['uid']] : [];
                        $info[$k]['goods'] = in_array($v['uid'],array_keys($goods)) ? $goods[$v['uid']] : [];
                    }
                }else{
                    foreach($info as $k => $v){
                        $info[$k]['auth'] = [];
                        $info[$k]['goods'] = [];
                    }
                }

                break;
            case 'vipshop' :
                $info = ShopModel::whereIn('id',$recommendId)
                    ->select('shop_pic', 'shop_name', 'shop_desc','id','uid')
                    ->get()->toArray();
                break;
            case 'article' :
            case 'news':
            case 'story' :
                $info = ArticleModel::whereIn('article.id',$recommendId)
                    ->leftJoin('article_category','article_category.id','=','article.cat_id')
                    ->leftJoin('cate','cate.id','=','article.cate_id')
                    ->where('article.status','1')
                    ->select('article.*','article_category.cate_name as cate_name','cate.name as appname')
                    ->get()->toArray();
                break;

        }
        $recommendArr = [];
        if(isset($info) && $recommend){
            if(empty($info)){
                return [];
            }
            $info = \CommonClass::setArrayKey($info,'id');
            foreach($recommend as $k => $v){
                if(in_array($v['recommend_id'],array_keys($info))){
                    $recommendArr[$k] = $v;
                    $recommendArr[$k]['info'] = $info[$v['recommend_id']];
                }
            }
        }

        return $recommendArr;
    }


    /**
     * 根据推荐类型查询已推荐的id
     * @param string $recommendType 推荐位类型
     * @return mixed
     */
    static function getRecommended($recommendType)
    {
        $recommend = RecommendModel::select('recommend.recommend_id')
            ->where('recommend.type',$recommendType)
            ->where('recommend.is_open',1)
            ->where(function($recommend){
                $recommend->where('recommend.end_time','0000-00-00 00:00:00')
                    ->orWhere('recommend.end_time','>',date('Y-m-d h:i:s',time()));
            })
            ->get()->toArray();
        if(empty($recommend)){
            return false;
        }
        $recommend_ids = array_unique(array_flatten($recommend));
        return $recommend_ids;
    }

    static function getRecommendedByPosition($position)
    {
        $recommend = RecommendModel::where('recommend.position_id',$position)
            ->where('recommend.is_open',1)
            ->where(function($recommend){
                $recommend->where('recommend.end_time','0000-00-00 00:00:00')
                    ->orWhere('recommend.end_time','>',date('Y-m-d h:i:s',time()));
            })
            ->lists('recommend_id')->toArray();
        if(empty($recommend)){
            return false;
        }
        return $recommend;
    }

}
