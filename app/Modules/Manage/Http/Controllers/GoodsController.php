<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\GoodsFollowModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserModel;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoodsController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('方案管理');
        $this->theme->set('manageType', 'auth');
    }

    /**
     * 方案列表视图
     *
     * @param Request $request
     * @return mixed
     */
    public function goodsList(Request $request)
    {
        $merge = $request->all();
        $goodsList = GoodsModel::whereRaw('1 = 1')->where('goods.is_delete',0);
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('goods.id', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%')
                    ->orWhere('user_detail.nickname', 'like', '%' . $keywords . '%');

            });
        }

        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];

        //方案类型
        if ($request->get('type')) {
            $goodsList = $goodsList->where('goods.type', $request->get('type'));
        }
        //方案状态态筛选
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $goodsList = $goodsList->where('goods.status', $request->get('status'));
        }
        //应用领域
        $cateArr = TaskCateModel::where('type',1)->get()->toArray();
        if ($request->get('cate_id')) {
            $goodsList = $goodsList->where('goods.cate_id', $request->get('cate_id'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('goods.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('goods.created_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'goods.created_at';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList->where('goods.is_delete',0)->leftJoin('users','users.id','=','goods.uid')->leftJoin('user_detail','user_detail.uid','=','goods.uid')->leftJoin('shop','shop.id','=','goods.shop_id')
            ->select('goods.*','users.name','users.mobile','user_detail.nickname','shop.shop_name')
            ->orderBy($by, $order)->paginate($paginate);
        //获取所有的seo 标签
        $seoList=SeoModel::all();
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
            'type_arr'   => $typeArr,
            'cate_arr'   => $cateArr,
            'seoList'    => $seoList,
        ];
        $this->theme->setTitle('方案列表');
        return $this->theme->scope('manage.shop.goodslist',$data)->render();
    }

    /**
     * 修改方案状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeGoodsStatus(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');
        $res = GoodsModel::changeGoodsStatus($id,$type);
        if($res){
            $data = array(
                'code' => 1,
                'msg' => 'success'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => 'failure'
            );
        }
        return response()->json($data);
    }

    /**
     * 方案审核失败
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkGoodsDeny(Request $request)
    {
        $reason = $request->get('reason');
        $type = 4;
        $id = $request->get('id');
        $res = GoodsModel::changeGoodsStatus($id,$type,$reason);
        if($res){
            $data = array(
                'code' => 1,
                'msg' => 'success'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => 'failure'
            );
        }
        return response()->json($data);
    }


    /**
     * 方案详情
     * @param int $id 商品id
     * @return mixed
     */
    public function goodsInfo($id)
    {
        $id = intval($id);
        //获取上一项id
        $preId = GoodsModel::where('id','>',$id)->min('id');
        //获取下一项id
        $nextId = GoodsModel::where('id','<',$id)->max('id');
        $goodsInfo = GoodsModel::getGoodsInfoById($id);
        $idsCateIdArr = explode(',',$goodsInfo->ide_cate_id);
        $deliveryCateIdArr = explode(',',$goodsInfo->delivery_cate_id);

        $cate = TaskCateModel::whereIn('type',[1,2,3,4])->get()->toArray();
        $cate = \CommonClass::setArrayKey($cate,'type',2);
        //应用领域
        $field = in_array(1,array_keys($cate)) ? $cate[1] : [];
        //技能标签
        $skill = in_array(2,array_keys($cate)) ? $cate[2] : [];
        //开发平台
        $plate = in_array(3,array_keys($cate)) ? $cate[3] : [];
        //交付形式
        $delivery = in_array(4,array_keys($cate)) ? $cate[4] : [];
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];

        $attachment = UnionAttachmentModel::where('object_id',$id)->where('object_type',5)->leftJoin('attachment','attachment.id','=','union_attachment.attachment_id')->select('attachment.*')->get()->toArray();
        $attachment = \CommonClass::setArrayKey($attachment,'id');

        //.方案PDF附件其他图片
        $gooddocPic = UnionAttachmentModel::leftJoin('attachment','union_attachment.attachment_id','=','attachment.id')
            ->where('union_attachment.object_id',$id)->where('union_attachment.object_type','7')->select('attachment.id','attachment.url')->get();
        foreach ($gooddocPic as $key => $value) {
            $gooddocPic[$key]['name'] = '附件'.($key+1);
        }
        //获取所有的seo 标签
        $seoList=SeoModel::all();
        //获取项目seo标签
        $taskSeo=SeoRelatedModel::where("related_id",$id)->where("type",2)->lists("seo_id")->toArray();

        $data = array(
            'goods_info' => $goodsInfo,
            'pre_id'     => $preId,
            'next_id'    => $nextId,
            'field'      => $field,
            'skill'      => $skill,
            'plate'      => $plate,
            'delivery'   => $delivery,
            'type_arr'   => $typeArr,
            'attachment' => $attachment,
            'idsCateIdArr' => $idsCateIdArr,
            'deliveryCateIdArr' => $deliveryCateIdArr,
            'seoList'      =>$seoList,
            'taskSeo'   =>$taskSeo,
            'gooddocPic'   =>$gooddocPic,
        );
        $this->theme->setTitle('方案详情');
        return $this->theme->scope('manage.shop.goodsinfo', $data)->render();
    }

    public function addGoods()
    {
        $cate = TaskCateModel::whereIn('type',[1,2,3,4])->get()->toArray();
        $cate = \CommonClass::setArrayKey($cate,'type',2);
        //应用领域
        $field = in_array(1,array_keys($cate)) ? $cate[1] : [];
        //技能标签
        $skill = in_array(2,array_keys($cate)) ? $cate[2] : [];
        //开发平台
        $plate = in_array(3,array_keys($cate)) ? $cate[3] : [];
        //交付形式
        $delivery = in_array(4,array_keys($cate)) ? $cate[4] : [];
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        // $user = UserModel::select('id','name','mobile','email')->with('detail')->get()->toArray();
        $user = [];
        $data = array(
            'field'      => $field,
            'skill'      => $skill,
            'plate'      => $plate,
            'delivery'   => $delivery,
            'type_arr'   => $typeArr,
            'user'       => $user
        );
        $this->theme->setTitle('方案添加');
        return $this->theme->scope('manage.shop.goodsadd', $data)->render();
    }

    /**
     * 方案跟进
     * @param Request $request
     * @param int $id 商品id
     * @return mixed
     */
    public function goodsComment(Request $request,$id)
    {
        $id = intval($id);

        $list = GoodsFollowModel::where('goods_id',$id)->paginate(10);
        $data = array(
            'id' => $id,
            'list' => $list
        );
        $this->theme->setTitle('方案跟进');
        return $this->theme->scope('manage.shop.goodsfollow', $data)->render();
    }

    public function goodsFollowAdd(Request $request)
    {
        $goodsId = $request->get('goods_id');
        $id = $request->get('id') ? $request->get('id') : '';
        $name = $this->manager['username'];
        $info = [];
        if($id){
            $info = GoodsFollowModel::find($id);
            $name = $info->manager_name;
        }
        $data = array(
            'info'       => $info,
            'goods_id'   => $goodsId,
            'id'         => $id,
            'name'       => $name
        );
        $this->theme->setTitle('方案跟进');
        return $this->theme->scope('manage.shop.goodsfollowadd', $data)->render();
    }

    /**
     * 保存方案跟进
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveGoodsFollow(Request $request)
    {
        $data = $request->except('_token');
        $data['time'] = date('Y-m-d H:i:s',strtotime(preg_replace('/([\x80-\xff]*)/i', '', $data['time'])));
        if(isset($data['id']) && !empty($data['id'])){
            $data['updated_at'] = date('Y-m-d H:i:s');
            $res = GoodsFollowModel::where('id',$data['id'])->update($data);
        }else{
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $res = GoodsFollowModel::create($data);
        }
        if($res){
            return redirect('/manage/goodsComment/'.$data['goods_id'])->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }

    public function goodsFollowDelete($id)
    {
        $res = GoodsFollowModel::where('id',$id)->delete();
        if($res){
            return redirect()->back()->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }
    /**
     * ajax获取二级行业分类
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetSecondCate(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $cate = TaskCateModel::findByPid([$id]);
        $data = [
            'cate' => $cate
        ];
        return response()->json($data);
    }

    /**
     * 修改方案信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveGoodsInfo(Request $request)
    {
        $data = $request->except('_token','seo_laber');
        $arr = [
            'id'                    => isset($data['id']) ? $data['id'] : '',
            'title'                 => $data['title'],
            'cate_id'               => $data['cate_id'],
            'type'                  => $data['type'],
            'is_customized'         => isset($data['type']) && $data['type'] == 1 && isset($data['is_customized']) ? $data['is_customized'] : 0,
            'ide_cate_id'           => isset($data['ide_cate_id']) && $data['ide_cate_id'] ? implode(',',$data['ide_cate_id']) : '',
            'skill_id'              => $data['skill_id'],
            'cash'                  => isset($data['type']) && $data['type'] == 1 && isset($data['cash']) ? $data['cash'] : '0.00',
            'freight'               => isset($data['type']) && $data['type'] == 1 && isset($data['freight']) ? $data['freight'] : '0.00',
            'application_scene'     => $data['application_scene'],
            'performance_parameter' => $data['performance_parameter'],
            'delivery_cate_id'      => isset($data['delivery_cate_id']) && $data['delivery_cate_id'] ? implode(',',$data['delivery_cate_id']) : '',
            'end_time'              => date('Y-m-d H:i:s',strtotime(preg_replace('/([\x80-\xff]*)/i', '', $data['end_time']))),
            'index_sort'            => $data['index_sort'],
            'status'                => isset($data['status']) ? $data['status'] : 0,
            'desc'                  => isset($data['desc']) ? $data['desc'] : '',
            'is_recommend'          => isset($data['is_recommend']) && $data['is_recommend'] ? $data['is_recommend'] : 0,
            'cover'                 => isset($data['is_default']) && $data['is_default'] ? $data['is_default'] : '',
            'sort'                  => isset($data['sort']) ? $data['sort'] : 0,
            'goods_grade'            => isset($data['goods_grade'])?$data['goods_grade']:'A',
        ];
        if(isset($data['uid'])){
            $arr['uid'] = $data['uid'];
            $shop = ShopModel::where('uid',$arr['uid'])->first();
            if($shop){
                $arr['shop_id'] = $shop->id;
            }
        }
        $fileIds = isset($data['file_ids']) && $data['file_ids'] ? $data['file_ids'] : [];
        $gooddoc = isset($data['GoodsDoc_ids']) && $data['GoodsDoc_ids'] ? $data['GoodsDoc_ids'] : [];//.方案文档
        $res = GoodsModel::saveGoodsInfo($arr,$fileIds,$gooddoc,$request);
        if($res){
            if (isset($data['id'])){
                return redirect('/manage/goodsInfo/'.$data['id'])->with(array('message' => '操作成功'));
            }else{
                return redirect('/manage/goodsList/')->with(array('message' => '操作成功'));
            }
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }


    /**
     * 商品流程配置视图
     * @return mixed
     */
    public function goodsConfig()
    {
        $goodsConfig = ConfigModel::getConfigByType('goods_config');
        $data = array(
            'goods_config' => $goodsConfig
        );
        $this->theme->setTitle('流程配置');
        return $this->theme->scope('manage.goodsconfig', $data)->render();
    }

    /**
     * 保存商品流程配置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postGoodsConfig(Request $request)
    {
        $data = $request->all();
        $configData = array(
            'min_price' => $data['min_price'],
            'trade_rate' => $data['trade_rate'],
            'legal_rights' => $data['legal_rights'],
            'doc_confirm' => $data['doc_confirm'],
            'comment_days' => $data['comment_days']
        );
        ConfigModel::updateConfig($configData);
        Cache::forget('goods_config');
        return redirect('/manage/goodsConfig')->with(array('message' => '操作成功'));
    }

}
