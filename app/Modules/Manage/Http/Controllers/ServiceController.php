<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\SubOrderModel;
use App\Modules\User\Model\UserToolModel;
use Illuminate\Http\Request;
use App\Modules\Manage\Http\Requests\ServiceRequest;
use Illuminate\Support\Facades\Auth;

class ServiceController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('增值工具管理');
        $this->theme->set('manageType', 'service');

    }

    /**
     * 增值工具列表
     * @param Request $request
     * @return mixed
     */
    public function serviceList(Request $request)
    {
        $serviceRes = ServiceModel::whereRaw('1 = 1');
        $by = $request->get('by') ? $request->get('by') : 'updated_at';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $serviceRes = $serviceRes->orderBy('type', 'asc')->orderBy($by, $order)->paginate($paginate);
        $serviceType = \CommonClass::getServiceType();
        $data = array(
            'service_list' => $serviceRes,
            'paginate' => $paginate,
            'serviceType' => $serviceType
        );
        // print_r($serviceType);
        $this->theme->setTitle('工具列表');
        return $this->theme->scope('manage.servicelist', $data)->render();
    }

    /**
     * 新建增值工具视图
     * @return mixed
     */
    public function addService()
    {
        $serviceType = \CommonClass::getServiceType();
        $data = array(
            'serviceType' => $serviceType
        );
        return $this->theme->scope('manage.addservice',$data)->render();
    }

    /**
     * 添加增值工具
     * @param Request $request
     * @return mixed
     */
    public function postAddService(ServiceRequest $request)
    {
        $data = $request->all();
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $data['updated_at'] = date('Y-m-d H:i:s',time());
        $file = $request->file('pic');
        if ($file){
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $data['pic'] = $result['data']['url'];
        }
        //添加信息
        $res = ServiceModel::create($data);
        if($res)
        {
            return redirect('manage/serviceList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 编辑增值工具视图
     * @param $id 自定义导航id
     * @return mixed
     */
    public function editService($id)
    {
        $id = intval($id);
        $serviceInfo = ServiceModel::where('id',$id)->first();
        $serviceType = \CommonClass::getServiceType();
        $data = array(
            'serviceInfo' => $serviceInfo,
            'serviceType' => $serviceType
        );
        return $this->theme->scope('manage.editservice',$data)->render();
    }

    /**
     * 编辑增值工具
     * @param Request $request
     * @return mixed
     */
    public function postEditService(ServiceRequest $request)
    {
        $data = $request->all();
        $arr = array(
            'title' => $data['title'],
            'price' => $data['price'],
            'description' => $data['description'],
            'status' => $data['status'],
            'type' => $data['type'],
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        $file = $request->file('pic');
        if ($file){
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $arr['pic'] = $result['data']['url'];
        }
        //修改信息
        $res = ServiceModel::where('id',$data['id'])->update($arr);
        if($res)
        {
            return redirect('manage/serviceList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 删除一个增值工具
     * @param $id 自定义导航id
     * @return mixed
     */
    public function deleteService($id)
    {
        $id = intval($id);
        $res = ServiceModel::where('id',$id)->delete();
        if(!$res)
        {
            return redirect()->to('/manage/serviceList')->with(array('message' => '操作失败'));
        }
        return redirect()->to('/manage/serviceList')->with(array('message' => '操作成功'));
    }

    /**
     * 购买记录
     * @param Request $request
     * @return mixed
     */
    public function serviceBuy(Request $request)
    {
        $arr = $request->all();
        $buyList = SubOrderModel::whereRaw('1 = 1');
        //编号筛选
        if($request->get('id')) {
            $buyList = $buyList->where('sub_order.id',$request->get('id'));
        }
        //购买用户
        if($request->get('name')) {
            $buyList = $buyList->where('u.name','like',"%".$request->get('name')."%");
        }
        //工具名称
        if($request->get('title')) {
            $buyList = $buyList->where('s.title','like',"%".$request->get('title')."%");
        }
        $start = '';
        $end = '';
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $buyList = $buyList->where('sub_order.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $buyList = $buyList->where('sub_order.created_at', '<', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'sub_order.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;


        $list = $buyList->join('service as s','sub_order.product_id','=','s.id' )->leftJoin('users as u','sub_order.uid','=','u.id')
            ->select('sub_order.id','sub_order.cash','sub_order.created_at','s.title','s.price','u.name','s.identify')
            ->orderBy($by, $order)->paginate($paginate);
        $listArr = $list->toArray();
        $data = array(
            'listobj' => $list,
            'list' => $listArr,
            'merge'=> $arr,
            'id' => $request->get('id'),
            'name' => $request->get('name'),
            'title' => $request->get('title'),
            'by' => $request->get('by'),
            'order' => $request->get('order'),
            'start' => $start,
            'end' => $end
        );
        return $this->theme->scope('manage.servicebuylist',$data)->render();
    }
    /*
     * 工具购买记录
     * */
    public function toolBuy(Request $request){

        $arr = $request->all();
        $buyList = UserToolModel::whereRaw('1 = 1');
        //编号筛选
        if($request->get('id')) {
            $buyList = $buyList->where('user_tool.id',$request->get('id'));
        }
        //购买用户
        if($request->get('name')) {
            $buyList = $buyList->where('u.name','like',"%".$request->get('name')."%");
        }
        //工具名称
        if($request->get('title')) {
            $buyList = $buyList->where('s.title','like',"%".$request->get('title')."%");
        }
        $start = '';
        $end = '';
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $buyList = $buyList->where('sub_order.created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $buyList = $buyList->where('sub_order.created_at', '<', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'user_tool.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $buyList->join('service as s','user_tool.tool_id','=','s.id' )->leftJoin('users as u','user_tool.uid','=','u.id')
            ->select('user_tool.id','user_tool.price','user_tool.created_at','s.title','s.price','u.name','s.identify')
            ->orderBy($by, $order)->paginate($paginate);
        $listArr = $list->toArray();
        $data = array(
            'listobj' => $list,
            'list' => $listArr,
            'merge'=> $arr,
            'id' => $request->get('id'),
            'name' => $request->get('name'),
            'title' => $request->get('title'),
            'by' => $request->get('by'),
            'order' => $request->get('order'),
            'start' => $start,
            'end' => $end
        );
        return $this->theme->scope('manage.toolBuyList',$data)->render();
    }
}

