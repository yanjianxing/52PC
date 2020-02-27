<?php

namespace App\Modules\Substation\Http\Controllers;

use App\Http\Controllers\SubstationController;
use App\Http\Requests;
use App\Modules\Manage\Model\SubstationModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserTagsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ServiceController extends SubstationController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('substation');
        //$this->initTheme('main');
    }

    /**
     * 分站服务商页面
     * @param Request $request
     * @param $substationId    分站地区id
     * @return mixed
     */
    public function getService(Request $request,$substationId)
    {
        //根据分站地区id获取地区名称
        $substation = SubstationModel::where('district_id', $substationId)->first();

        if(!empty($substation)){
            $substationName = $substation->name;
        }else{
            $substationName = '全国';
        }
        if(Session::get('substation_name')){
            Session::forget('substation_name');
            Session::put('substation_name',$substationName);
        }else{
            Session::put('substation_name',$substationName);
        }
        $this->theme->set('substationID',$substationId);
        $this->theme->set('substationNAME',$substationName);
        $this->theme->setTitle($substationName.'服务商');

        $merge = $request->all();

        $list = UserModel::select('user_detail.sign', 'users.name', 'user_detail.avatar', 'users.id','users.email_status',
            'user_detail.employee_praise_rate','user_detail.shop_status','shop.is_recommend','shop.id as shopId')
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->leftJoin('shop','user_detail.uid','=','shop.uid')->where('users.status','<>', 2);

        if($request->get('service_name')){
            $searchName = $request->get('service_name');
            $list = $list->where('users.name','like',"%".$searchName."%");
        }
        //服务商筛选
        if ($request->get('category')) {
            $category = TaskCateModel::findByPid([$request->get('category')]);

            if (empty($category)) {
                $category_data = TaskCateModel::findById($request->get('category'));
                $category = TaskCateModel::findByPid([$category_data['pid']]);
                $pid = $category_data['pid'];
                $arrTag = TagsModel::where('cate_id', $request->get('category'))->lists('id')->toArray();
                $dataUid = UserTagsModel::whereIn('tag_id', $arrTag)->lists('uid')->toArray();
                $list = $list->whereIn('users.id', $dataUid);
            } else {
                foreach ($category as $item){
                    $arrCateId[] = $item['id'];
                }
                $arrTag = TagsModel::whereIn('cate_id', $arrCateId)->lists('id')->toArray();
                $dataUid = UserTagsModel::whereIn('tag_id', $arrTag)->lists('uid')->toArray();
                $list = $list->whereIn('users.id', $dataUid);
                $pid = $request->get('category');
            }
        } else {
            //查询一级的分类,默认的是一级分类
            $category = TaskCateModel::findByPid([0]);
            $pid = 0;
        }

        //好评数降序排列
        if($request->get('employee_praise_rate') && $request->get('employee_praise_rate') == 1){
            $list = $list->orderby('user_detail.employee_praise_rate','DESC');
        }
        $paginate = 10;
        //根据分站id查询分站的店铺和服务商
        $this->substation = $substationId;
        $list = $list->where(function($list){
                    $list->where('user_detail.city',$this->substation)->orWhere('shop.city',$this->substation)
                    ->orwhere('user_detail.province',$this->substation)->orwhere('shop.province',$this->substation);
                });
        $list = $list->orderBy('shop.is_recommend','DESC')->paginate($paginate);
        if (!empty($list->toArray()['data'])){

            foreach ($list as $k => $v){
                $arrUid[] = $v->id;
            }
        } else {
            $arrUid = 0;
        }

        //查询所有评价数组
        $comment = CommentModel::whereIn('to_uid',$arrUid)->get()->toArray();
        if(!empty($comment)){
            //根据uid重组数组
            $newComment = array_reduce($comment,function(&$newComment,$v){
                $newComment[$v['to_uid']][] = $v;
                return $newComment;
            });
            $commentCount = array();
            if(!empty($newComment)){
                foreach($newComment as $c => $d){
                    $commentCount[$c]['to_uid'] = $c;
                    $commentCount[$c]['count'] = count($d);
                }
            }
            //查询好评评价数组
            $goodComment = CommentModel::whereIn('to_uid',$arrUid)->where('type',1)->get()->toArray();
            //根据uid重组数组
            $newGoodsComment = array_reduce($goodComment,function(&$newGoodsComment,$v){
                $newGoodsComment[$v['to_uid']][] = $v;
                return $newGoodsComment;
            });
            $goodCommentCount = array();
            if(!empty($newGoodsComment)){
                foreach($newGoodsComment as $a => $b){
                    $goodCommentCount[$a]['to_uid'] = $a;
                    $goodCommentCount[$a]['count'] = count($b);
                }
            }
            //把好评数和评价数拼入$list数组
            foreach($list as $key => $value){
                foreach($goodCommentCount as $a => $b){
                    if($value['id'] == $b['to_uid']){
                        $list[$key]['good_comment_count'] = $b['count'];
                    }
                }
                foreach($commentCount as $c => $d){
                    if($value['id'] == $d['to_uid']){
                        $list[$key]['comment_count'] = $d['count'];
                    }
                }
            }
            foreach ($list as $key => $item) {
                //计算好评率
                if($item->comment_count > 0){
                    $item->percent = ceil($item->good_comment_count/$item->comment_count*100);
                }
                else{
                    $item->percent = 100;
                }
            }
        }else{
            foreach ($list as $key => $item) {
                //计算好评率
                $item->percent = 100;
            }
        }

        //查询行业标签
        $arrSkill = UserTagsModel::getTagsByUserId($arrUid);

        if(!empty($arrSkill) && is_array($arrSkill)){
            foreach ($arrSkill as $item){
                $arrTagId[] = $item['tag_id'];
            }

            $arrTagName = TagsModel::select('id', 'tag_name')->whereIn('id', $arrTagId)->get()->toArray();
            foreach ($arrSkill as $item){
                foreach ($arrTagName as $value){
                    if ($item['tag_id'] == $value['id']){
                        $arrUserTag[$item['uid']][] = $value['tag_name'];
                    }
                }
            }
            foreach ($list as $key => $item){
                foreach ($arrUserTag as $k => $v){
                    if ($item->id == $k){
                        $list[$key]['skill'] = $v;
                    }
                }
            }
        }

        //查询服务商的认证情况
        $userAuthOne = AuthRecordModel::whereIn('uid', $arrUid)->where('status', 2)->whereIn('auth_code',['bank','alipay'])->get()->toArray();
        $userAuthTwo = AuthRecordModel::whereIn('uid', $arrUid)->where('status', 1)
            ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
        $userAuth = array_merge($userAuthOne,$userAuthTwo);
        $auth = array();
        if(!empty($userAuth) && is_array($userAuth)){
            foreach($userAuth as $a => $b){
                foreach($userAuth as $c => $d){
                    if($b['uid'] = $d['uid']){
                        $auth[$b['uid']][] = $d['auth_code'];
                    }
                }
            }
        }
        if(!empty($auth) && is_array($auth)){
            foreach($auth as $e => $f){
                $auth[$e]['uid'] = $e;
                if(in_array('realname',$f)){
                    $auth[$e]['realname'] = true;
                }else{
                    $auth[$e]['realname'] = false;
                }
                if(in_array('bank',$f)){
                    $auth[$e]['bank'] = true;
                }else{
                    $auth[$e]['bank'] = false;
                }
                if(in_array('alipay',$f)){
                    $auth[$e]['alipay'] = true;
                }else{
                    $auth[$e]['alipay'] = false;
                }
                if(in_array('enterprise',$f)){
                    $auth[$e]['enterprise'] = true;
                }else{
                    $auth[$e]['enterprise'] = false;
                }
            }
            foreach ($list as $key => $item) {
                //拼接认证信息
                foreach ($auth as $a => $b) {
                    if ($item->id == $b['uid']) {
                        $list[$key]['auth'] = $b;
                    }
                }
            }
        }

        //分站最新服务商
        $newShop = UserModel::select('user_detail.sign', 'users.name', 'user_detail.avatar', 'users.id',
            'users.email_status','user_detail.employee_praise_rate','user_detail.shop_status','shop.is_recommend','shop.id as shopId')
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->leftJoin('shop','users.id','=','shop.uid')
            ->where('users.status','<>', 2)
            ->where(function($list){
                $list->where('user_detail.city',$this->substation)->orWhere('shop.city',$this->substation)
                    ->orwhere('user_detail.province',$this->substation)->orwhere('shop.province',$this->substation);
            })
            ->orderBy('shop.created_at','DESC')
            ->limit(5)->get()->toArray();
        if(count($newShop)){
            foreach($newShop as $k=>$v){
                $comment = CommentModel::where('to_uid',$v['id'])->count();
                $goodComment = CommentModel::where('to_uid',$v['id'])->where('type',1)->count();
                if($comment){
                    $v['percent'] = intval(($goodComment/$comment)*100);
                }
                else{
                    $v['percent'] = 100;
                }
                $newShop[$k] = $v;
            }
            $hotList = $newShop;
        }
        else{
            $hotList = [];
        }

        $this->theme->set('menu_type',2);
        $data = array(
            'pid' => $pid,
            'category' => $category,
            'list' => $list,
            'merge' => $merge,
            'paginate' => $paginate,
            'page' => $request->get('page') ? $request->get('page') : '',
            'skillId' => $request->get('skillId') ? $request->get('skillId') : '',
            'type' => $request->get('type') ? $request->get('type') : 0,
            'hotList' => $hotList,
            'substation_id' => $substationId,
            'substation_name' => $substationName
        );
        return $this->theme->scope('substation.service', $data)->render();
    }
}
