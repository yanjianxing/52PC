<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/31
 * Time: 13:47
 */
namespace App\Modules\Advertisement\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Advertisement\Model\AdRecomeTypeModel;
use App\Modules\Advertisement\Model\RePositionModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ArticleCategoryModel;
use Illuminate\Http\Request;
use Theme;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Manage\Model\SpecialModel;
use App\Modules\Manage\Model\SpecialNewsModel;
use Validator;

class RecommendController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('推荐管理');
    }

    /*
     * 推荐位列表
     * */
    public function recommendList(Request $request)
    {
        $merge = $request->all();
        $typeArr = AdRecomeTypeModel::where('status',1)->select('id','name')->get()->toArray();
        $typeId = $request->get('type_id') ? $request->get('type_id') : ($typeArr[0] ? $typeArr[0]['id'] : 1);
        $merge['type_id'] = $typeId;
        $recommendList = RePositionModel::where('is_open','1')->where('type_id',$typeId)->paginate(10);
        foreach($recommendList->items() as $k=>$v){
            $deliveryNum = RecommendModel::where('position_id',$v->id)
                ->where('is_open','1')
                ->where(function($deliveryNum){
                    $deliveryNum->where('end_time','0000-00-00 00:00:00')
                        ->orWhere('end_time','>',date('Y-m-d h:i:s',time()));
                })
                ->count();
            if($deliveryNum){
                $v->deliveryNum = $deliveryNum;
            }
            else{
                $v->deliveryNum = 0;
            }
        }
        $view = [
            'recommendList' => $recommendList,
            'typeArr'       => $typeArr,
            'merge'         => $merge
        ];
        return $this->theme->scope('manage.ad.rePositionList',$view)->render();
    }

    /**
     * 修改推荐位的名称
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function nameUpdate(Request $request){
        $RecommendDetail = RePositionModel::find(intval($request->get('id')));
        $data = array();
        if(!$RecommendDetail){
            $data['status'] = 'fail';
        }
        $newdata = [
            'name' => $request->get('name')
        ];
        $res = $RecommendDetail->update($newdata);
        if($res){
            $data['status'] = 'success';
        }
        else{
            $data['status'] = 'fail';
        }
        return $data;
    }


    /**
     * 所有推荐位下的服务商列表
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function serverList(Request $request){
        $by = $request->get('by')?$request->get('by'):'sort';
        $order = $request->get('order') ? $request->get('order') : 'asc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $serviceList = RecommendModel::leftJoin('recommend_position','recommend_position.id','=','recommend.position_id')
            ->where('recommend.is_open','<>','3')
            ->select('recommend.*','recommend_position.name','recommend_position.position')
            ->orderBy($by, $order);
        if ($request->get('recommend_name')) {
            $serviceList = $serviceList->where('recommend.recommend_name','like','%'.$request->get('recommend_name').'%');
        }

        if($request->get('position_id') != 0){
            $serviceList = $serviceList->where('recommend.position_id','=',$request->get('position_id'));

        }

        if($request->get('is_open') != 0){
            $serviceList = $serviceList->where('recommend.is_open','=',$request->get('is_open'));
        }
        $serviceList = $serviceList->orderBy('recommend.sort','asc')->orderBy('recommend.created_at','desc')->paginate($paginate);

        $positionInfo = RePositionModel::select('id','name')->get();

        $view = array(
            'serviceList'  => $serviceList,
            'search'       => $request->all(),
            'positionInfo' => $positionInfo
        );

        return $this->theme->scope('manage.ad.recommendlist', $view)->render();
    }

    /**
     * 删除某个服务商信息
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function deleteReInfo($id){
        $recommendInfo = RecommendModel::find($id);
        if(empty($recommendInfo)){
            return redirect()->back()->with(['error'=>'传送参数错误！']);
        }
        $res = $recommendInfo->update(['is_open' => '3']);
        if($res){
            return redirect()->back()->with(['message'=>'删除成功！']);
        }
        else{
            return redirect()->back()->with(['message'=>'删除失败！']);
        }
    }

    /**
     * 跳转到创建服务商页面
     * @return mixed
     */
    public function insertRecommend(Request $request){
        $positionInfo = RePositionModel::where('is_open',1)->select('id','name','code','position')->get();
        $domain = \CommonClass::domain();
        $view = [
            'positionId'   => $request->get('positionId') ? $request->get('positionId') : 0,
            'positionInfo' => $positionInfo,
            'domain'       => $domain
        ];
        return $this->theme->scope('manage.ad.adrecommend',$view)->render();
    }

    /**
     * 创建服务商信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addRecommend(Request $request){
        $data = $request->except('_token');
        $validator = Validator::make($request->all(), [
            'type'         => 'required',
            'position_id'  => 'required',
            'recommend_id' => 'required',
            //'recommend_pic' => 'required',
            'url'          => 'required|url'
        ],[
            'type.required'         => '请选择推荐分类',
            'position_id.required'  => '请选择推荐位置',
            'recommend_id.required' => '请选择推荐名称',
            //'recommend_pic.required' => '请上传图片',
            'url.required'          => '请输入链接',
            'url.url'               => '请输入有效的url'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return redirect()->back()->with(['error'=>$validator->errors()->first()]);
        }
        if(!$request->get('position_id')){
            return redirect()->back()->with(['error'=>'请选择推荐位置']);
        }

        $ad_num = RePositionModel::where('id',intval($data['position_id']))->select('num')->get();
        $num = RecommendModel::where('position_id',intval($data['position_id']))
            ->where(function($num){
                $num->where('end_time','0000-00-00 00:00:00')
                    ->orWhere('end_time','>',date('Y-m-d H:i:s',time()));
            })
            ->where('is_open',1)
            ->count();
        if(isset($ad_num[0]) && $ad_num[0]['num'] <= $num){
            $errorData['message'] = '该推荐位已满';
            return redirect()->back()->with(['error'=>'该推荐位已满！']);
        }

        $file = $request->file('recommend_pic');
        if(!in_array($data['type'],['shop','work','server','successcase','task'])){
            if(empty($file)){
                return redirect()->back()->with(['error'=>'请上传图片']);
            }

        }
        $url = '';
        if((!in_array($data['type'],['shop','work','server','successcase','task'])) ||
            (in_array($data['type'],['shop','work','server','successcase','task']) && !empty($file))){
            //上传文件
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            if($result['code'] != 200){
                return redirect()->back()->with(['error'=>'图片上传失败']);
            }
            $url = $result['data']['url'];
        }

        $name = '';
        switch($data['type']){
            case 'service':
                $recommend_name = UserModel::find($data['recommend_id']);
                $name = $recommend_name->name;
                break;
            case 'successcase':
                $recommend_name = SuccessCaseModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'article':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'news':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'story':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'special':
                $recommend_name = SpecialModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'task':
                $recommend_name = TaskModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'shop':
                $recommend_name = ShopModel::find($data['recommend_id']);
                $name = $recommend_name->shop_name;
                break;
            case 'goods':
                $recommend_name = GoodsModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'vipshop':
                $recommend_name = ShopModel::find($data['recommend_id']);
                $name = $recommend_name->shop_name;
                break;
        }
        $data['start_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['start_time']);
        $data['end_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['end_time']);
        $newData = [
            'position_id'       => $data['position_id'],
            'type'              => $data['type'],
            'recommend_id'      => $data['recommend_id'],
            'recommend_name'    => $name,
            'recommend_type'    => $data['recommend_type'],
            'recommend_pic'     => empty($file)?'':$url,
            'url'               => $data['url'],
            'start_time'        => date('Y-m-d H:i:s',strtotime($data['start_time'])),
            'end_time'          => date('Y-m-d H:i:s',strtotime($data['end_time'])),
            'sort'              => $data['sort'],
            'is_open'           => $data['is_open'],
            'created_at'        => date('Y-m-d H:i:s',time())

        ];
        $res = RecommendModel::create($newData);
        if($res){
            return redirect('/advertisement/serverList?position_id='.$data['position_id'])->with(['message'=>'创建成功！']);
        }
        return redirect()->back()->with(['message'=>'创建失败！']);
    }

    /*
     * 跳转到修改服务商页面
     * */
    public function updateRecommend($id){
        $positionInfo = RePositionModel::where('is_open',1)->select('id','name','code','position')->get();
        $serviceInfo = RecommendModel::where('id',$id)->select('*')->get();
        $userInfo = [];
        switch($serviceInfo[0]->type){

            case 'successcase':
                $userInfo = SuccessCaseModel::select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'article':
                $userInfo = ArticleModel::select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'story':
                $userInfo = ArticleModel::select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended = array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                $userInfo = $userInfo->orderBy('id','desc')->limit(1000)->get();
                break;
            case 'task':
                $userInfo = TaskModel::select('id','title')->where('task.type_id',1);;
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'shop':
                $userInfo = ShopModel::where('status',1)->select('id','shop_name');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'vipshop':
                $uidArr = UserModel::where('status',1)->where('level','>',1)->lists('id')->toArray();
                $userInfo = ShopModel::whereIn('uid',$uidArr)->where('status',1)->select('id','shop_name');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'goods':
                $userInfo = GoodsModel::where(['status' => 1,'is_delete' => 0])->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
            case 'special':
                $userInfo = SpecialModel::where(['status' => 1])->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($serviceInfo[0]->position_id);
                if($recommended != false){
                    $this->recommededId = $serviceInfo[0]['recommend_id'];
                    $recommended =array_where($recommended,function ($key, $value) {
                        return $value != $this->recommededId;
                    });
                    $userInfo = $userInfo->whereNotIn('id',$recommended);
                }
                // $userInfo = $userInfo->get()->chunk(100);
                break;
        }
        $domain = \CommonClass::domain();
        $userInfo = [];
        $view = [
            'positionInfo' => $positionInfo,
            'serviceInfo'  => $serviceInfo,
            'service_id'   => $id,
            'userInfo'     => $userInfo,
            'domain'       => $domain
        ];
        return $this->theme->scope('manage.ad.recommendedit',$view)->render();
    }
    /**
     * 搜索推荐信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function searchInfo(request $request){
        $keyword = $request->get('nickname');
        $type = $request->get('typeVal');
        $positionId = $request->get('position_id');
        $data = [];
        if(!$keyword){
            return $data = [
                'code' => 0,
                'msg'  => '请输入用户昵称'
            ];
        }
        if(!$type){
            return $data = [
                'code' => 0,
                'msg'  => '请选择推荐分类'
            ];
        }
        switch($type){
            case 'successcase'://成功案例
                $list = SuccessCaseModel::where('status',2)->where('title','like','%'.$keyword.'%')->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->get()->toArray();
                if($list){
                    $html = '';
                    foreach($list as $key => $val){
                        $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $list[0]['id'],
                        'middleUrl' => '/anli/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'article'://方案讯
                $list = ArticleModel::select('id','title')->where('status','1')->where('cat_id','!=','73')->where('title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->get()->toArray();
                if($list){
                    $html = '';
                    foreach($list as $key => $val){
                        $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $list[0]['id'],
                        'middleUrl' => '/news/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'news'://方案讯
                $list = ArticleModel::select('id','title')->where('status','1')->where('cat_id','!=','73')->where('title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->get()->toArray();
                if($list){
                    $html = '';
                    foreach($list as $key => $val){
                        $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $list[0]['id'],
                        'middleUrl' => '/news/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'special'://专题
                $list = SpecialModel::select('id','title')->where('status','1')->where('title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->get()->toArray();
                if($list){
                    $html = '';
                    foreach($list as $key => $val){
                        $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $list[0]['id'],
                        'middleUrl' => '/news/special/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'story'://快包故事
                $catId = ArticleCategoryModel::where('cate_name','快包故事')->pluck('id');
                $list = ArticleModel::where('cat_id',$catId)->select('id','title')->where('title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->get()->toArray();
                if($list){
                    $html = '';
                    foreach($list as $key => $val){
                        $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $list[0]['id'],
                        'middleUrl' => '/news/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'task'://快包项目
                $taskList = TaskModel::select('task.id','task.title')->where('task.type_id',1)->where('task.title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $taskList = $taskList->whereNotIn('id',$recommended);
                }
                $taskList = $taskList->where('is_del',0)->where('is_open',1)
                    ->where('task.status','>=',2)->where('task.status','!=',3)->where('task.status','!=',10)->orderBy('id','desc')->get()->toArray();
                if($taskList){
                    $html = '';
                    foreach($taskList as $key => $val){
                            $html = $html.'<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $taskList[0]['id'],
                        'middleUrl' => '/kb/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'shop'://服务商
                $shopInfo = ShopModel::where('status',1)->where('shop_name','like','%'.$keyword.'%')->select('id','shop_name');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $shopInfo = $shopInfo->whereNotIn('id',$recommended);
                }
                $shopInfo = $shopInfo->get()->toArray();
                if($shopInfo){
                    $html = '';
                    foreach($shopInfo as $key => $val){
                            $html = $html.'<option value="'.$val['id'].'">'.$val['shop_name'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $shopInfo[0]['id'],
                        'middleUrl' => '/fuwus/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'vipshop'://VIP服务商
                $uidIdArr = UserModel::where('status',1)->where('level','>',1)->lists('id')->toArray();
                $shopInfo = ShopModel::whereIn('uid',$uidIdArr)->where('status',1)->where('shop_name','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);

                if($recommended != false){
                    $shopInfo = $shopInfo->whereNotIn('id',$recommended);
                }
                $shopInfo = $shopInfo->get()->toArray();
                if($shopInfo){
                    $html = '';
                    foreach($shopInfo as $key => $val){
                            $html = $html.'<option value="'.$val['id'].'">'.$val['shop_name'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $shopInfo[0]['id'],
                        'middleUrl' => '/fuwus/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
            case 'goods'://方案
                $workInfo = GoodsModel::where(['status' => 1,'is_delete' => 0])->select('id','title')->where('title','like','%'.$keyword.'%');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $workInfo = $workInfo->whereNotIn('id',$recommended);
                }
                $workInfo = $workInfo->get()->toArray();
                if($workInfo){
                    $html = '';
                    foreach($workInfo as $key => $val){
                            $html = $html . '<option value="'.$val['id'].'">'.$val['title'].'</option>';
                    }
                    return $data = [
                        'code' => 1,
                        'msg'  => 'success',
                        'data' => $html,
                        'recommendid' => $workInfo[0]['id'],
                        'middleUrl' => '/facs/'
                    ];
                }
                else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '没有匹配的信息'
                    ];
                }
                break;
        }
        return $data;
    }

    /**
     * 修改服务商信息
     *
     * @param Request $request,$service_id
     * @return \Illuminate\Http\Response
     */
    public function modifyRecommend(Request $request,$service_id){
        if(!$service_id){
            return redirect()->back()->with(['error'=>'传送参数不能为空！']);
        }
        $recommendInfo = RecommendModel::find(intval($service_id));
        if(!$recommendInfo){
            return redirect()->back()->with(['error'=>'传送参数错误！']);
        }
        $validator = Validator::make($request->all(), [
            'type'         => 'required',
            'position_id'  => 'required',
            'recommend_id' => 'required',
            'url'          => 'required|url'
        ],[
            'type.required'         => '请选择推荐分类',
            'position_id.required'  => '请选择推荐位置',
            'recommend_id.required' => '请选择推荐名称',
            'url.required'          => '请输入链接',
            'url.url'               => '请输入有效的url'
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return redirect()->back()->with(['error'=>$validator->errors()->first()]);
        }

        if(!$request->get('position_id')){
            return redirect()->back()->with(['error'=>'请选择推荐位置']);
        }
        $data = $request->except('_token');
        $file = $request->file('recommend_pic');
        if(!empty($file)){
            //上传文件
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }else{
            $pic = $recommendInfo['recommend_pic'];
        }
        $name = '';
        switch($data['type']){
            case 'vipshop':
                $recommend_name = ShopModel::find($data['recommend_id']);
                $name = $recommend_name->shop_name;
                break;
            case 'successcase':
                $recommend_name = SuccessCaseModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'article':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'news':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'story':
                $recommend_name = ArticleModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'task':
                $recommend_name = TaskModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'shop':
                $recommend_name = ShopModel::find($data['recommend_id']);
                $name = $recommend_name->shop_name;
                break;
            case 'goods':
                $recommend_name = GoodsModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
            case 'special':
                $recommend_name = SpecialModel::find($data['recommend_id']);
                $name = $recommend_name->title;
                break;
        }
        $data['start_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['start_time']);
        $data['end_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['end_time']);
        $newData = [
            'position_id'       => $data['position_id'],
            'type'              => $data['type'],
            'recommend_id'      => $data['recommend_id'],
            'recommend_name'    => $name,
            'recommend_type'    => $data['recommend_type'],
            'recommend_pic'     => $pic,
            'url'               => $data['url'],
            'start_time'        => date('Y-m-d h:i:s',strtotime($data['start_time'])),
            'end_time'          => date('Y-m-d h:i:s',strtotime($data['end_time'])),
            'sort'              => $data['sort'],
            'is_open'           => $data['is_open']

        ];
        $res = $recommendInfo->update($newData);
        if($res){
            return redirect('/advertisement/serverList?position_id='.$data['position_id'])->with(['message'=>'修改成功！']);
        }
        else{
            return redirect()->back()->with(['message'=>'修改失败！']);
        }
    }

    /**
     * 获取推荐信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getReInfo(Request $request){
        $type = $request->get('type');
        $positionId = $request->get('positionId');
        $option = '';
        switch($type){
            case 'successcase'://成功案例
                $list = SuccessCaseModel::where('status',2)->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->get()->chunk(100);
                if($list){
                    foreach($list as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'article'://方案讯
                $list = ArticleModel::select('id','title')->where('status','1')->where('cat_id','!=','73');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->limit(200)->get()->chunk(100);
                if($list){
                    foreach($list as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'news'://方案讯
                $list = ArticleModel::select('id','title')->where('status','1');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->orderBy('id','desc')->limit(50)->get()->chunk(100);
                if($list){
                    foreach($list as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'special'://专题
                $list = SpecialModel::select('id','title')->where('status','1');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->get()->chunk(100);
                if($list){
                    foreach($list as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'story'://快包故事
                $catId = ArticleCategoryModel::where('cate_name','快包故事')->pluck('id');
                $list = ArticleModel::where('cat_id',$catId)->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $list = $list->whereNotIn('id',$recommended);
                }
                $list = $list->get()->chunk(100);
                if($list){
                    foreach($list as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'task'://快包项目
                $taskList = TaskModel::select('task.id','task.title')->where('task.type_id',1);
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $taskList = $taskList->whereNotIn('id',$recommended);
                }
                $taskList = $taskList->where('is_del',0)->where('is_open',1)
                    ->where('task.status','>=',2)->where('task.status','!=',3)->where('task.status','!=',10)->orderBy('id','desc')->limit(50)->get()->chunk(100);
                if($taskList){
                    foreach($taskList as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'shop'://服务商
                $shopInfo = ShopModel::where('status',1)->where('is_recommend','1')->select('id','shop_name');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $shopInfo = $shopInfo->whereNotIn('id',$recommended);
                }
                $shopInfo = $shopInfo->get()->chunk(100);
                if($shopInfo){
                    foreach($shopInfo as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['shop_name'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'vipshop'://VIP服务商
                $uidIdArr = UserModel::where('status',1)->where('level','>',1)->lists('id')->toArray();
                $shopInfo = ShopModel::whereIn('uid',$uidIdArr)->where('status',1);
                $recommended = RecommendModel::getRecommendedByPosition($positionId);

                if($recommended != false){
                    $shopInfo = $shopInfo->whereNotIn('id',$recommended);
                }
                $shopInfo = $shopInfo->get()->chunk(100);
                if($shopInfo){
                    foreach($shopInfo as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['shop_name'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
            case 'goods'://方案
                $workInfo = GoodsModel::where(['status' => 1,'is_delete' => 0])->select('id','title');
                $recommended = RecommendModel::getRecommendedByPosition($positionId);
                if($recommended != false){
                    $workInfo = $workInfo->whereNotIn('id',$recommended);
                }
                $workInfo = $workInfo->get()->chunk(100);
                if($workInfo){
                    foreach($workInfo as $key => $val){
                        foreach($val as $k => $v){
                            $option .= '<option value="'.$v['id'].'">'.$v['title'].'</option>';
                        }
                    }
                    return $option;
                }
                else{
                    return $option;
                }
                break;
        }
        return $option;

    }


}
