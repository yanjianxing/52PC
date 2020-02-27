<?php

namespace App\Modules\Manage\Model;

use App\Modules\Manage\Model\ArticleCategoryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ArticlePayModel;

class ArticleModel extends Model
{
    //
    protected $table = 'article';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','cat_id','cate_id','title','user_id','user_name','author','publisher','summary','content','pr_leader','status','technology_id','online_time','view_times','from','fromurl','url','thumb','tag','pic','display_order','seotitle','keywords','description','created_at','updated_at','is_recommended','attachment_id','articlefrom','reprint_url',"price",'is_delete','reason','verified_at','is_free'
    ];

    public $timestamps = false;

    public function skill()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskCateModel','id','cate_id');
    }
    /**
     * 获取方案讯息列表
     * @param int $paginate
     * @param array $merge
     * @return mixed
     */
    static public function getArticleList($paginate=10,$merge=[])
    {
        $catIdArr = ArticleCategoryModel::where('pid',1)->lists('id')->toArray();
        $list = ArticleModel::whereIn('cat_id',$catIdArr)->where("status",1);
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $list = $list->where('title','like','%'.$merge['keywords'].'%');
        }
        if(isset($merge['cate_id']) && !empty($merge['cate_id'])){
            $list = $list->where('cate_id',$merge['cate_id']);
        }
        if(isset($merge['uid']) && !empty($merge['uid'])){
            $list = $list->where('user_id',$merge['uid']);
        }
        if(isset($merge['is_skill']) && !empty($merge['is_skill'])){
            $list = $list->with('skill');
        }

        if(isset($merge['order']) && $merge['order']){
            $list = $list->orderBy($merge['order'],'desc');
        }
        if(isset($merge['relatedId'])){
            $list = $list->whereIn("id",$merge['relatedId']);
        }
        $list = $list->orderBy('id','desc')->paginate($paginate);

        return $list;
    }

    /**
     * 支付资讯费用
     * @param $money
     * @param $employ_id
     * @param $uid
     * @param $code
     * @param int $type
     * @return bool
     */
    static public function payarticle($money, $employ_id, $uid, $code="", $type = 1,$payAccount='',$couponPrice=0)
    {
        $status = DB::transaction(function () use ($money, $employ_id,$code, $uid,$type,$payAccount,$couponPrice) {
            if($type == 1){//余额支付扣除用户的余额
                DB::table('user_detail')->where('uid', '=', $uid)->where('balance_status', '!=', 1)->decrement('balance', $money);
            }
            $balance = DB::table('user_detail')->where('uid', '=', $uid)->first()->balance;
            //生成财务记录，action 7表示发布任务支付
            $financial = [
                'action' => 7,
                'pay_type' => $type,
                'coupon'=>$couponPrice,
                'cash' => $money+$couponPrice,
                'uid' => $uid,
                'status' => '2',
                'related_id'=>$employ_id,
                'remainder' => $balance,
                'created_at' => date('Y-m-d H:i:s', time()),
                'pay_account'=>$payAccount,
            ];
            FinancialModel::create($financial);
            //修改资讯状态
            ArticleModel::where('id',$employ_id)->update(['status'=>1]);
            if($code){  //余额支付没有订单
                //修改订单状态
                ArticlePayModel::where('order_num','=', $code)->update(['status' => 2,'payment_at'=>date('Y-m-d H:i:s', time())]);
            }
            
        });
        return is_null($status) ? true : false;
    }

    /*
    *资讯审核不通过退款
    * return $status
    */
    static public function refund($data){
         $ispay = ArticlePayModel::where('article_id','=',$data['article_id'])->where('uid','=',$data['uid'])->where('price','=',$data['price'])->where('status','=',2)->first();
         $isfinancial = FinancialModel::where('related_id','=',$data['article_id'])->where('uid','=',$data['uid'])->where('cash','=',$data['price'])->where('action','=',7)->first();
         if($ispay && $isfinancial){    //资讯已付款
            $status = DB::transaction(function () use ($data) {
                //退款到平台账户
                DB::table('user_detail')->where('uid', '=', $data['uid'])->where('balance_status', '!=', 1)->increment('balance', $data['price']);
                $balance = DB::table('user_detail')->where('uid', '=', $data['uid'])->first()->balance;
                //生成财务记录，action 15表示资讯退款
                $financial = [
                    'action' => 15,
                    'pay_type' => 1,
                    'cash' => $data['price'],
                    'uid' => $data['uid'],
                    'status' => '1',
                    'related_id'=>$data['article_id'],
                    'remainder' => $balance,
                    'created_at' => date('Y-m-d H:i:s', time())
                ];
                FinancialModel::create($financial);
                //修改订单状态
                ArticlePayModel::where('article_id','=',$data['article_id'])->where('uid','=',$data['uid'])->where('price','=',$data['price'])->where('status','=',2)->update(['status' => 3]);
            });
            return is_null($status) ? true : false;
         }else{
            return true;
         }
         return true;
    }
}
