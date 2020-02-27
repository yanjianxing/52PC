<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Http\Controllers\BasicController;
use App\Modules\User\Model\DistrictModel;
use Illuminate\Http\Request;

class AreaController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('地区管理');
        $this->theme->set('manageType', 'area');
    }
    /**
     * 地区列表
     */
	public function areaList(Request $request)
    {
        $province_data = DistrictModel::findTree(0);

        $data = [
            'province_data'=>$province_data,
        ];

        return $this->theme->scope('manage.arealist', $data)->render();
    }

    /**
     * 删除一个地区数据
     * @param $id
     */
    public function areaDelete($id)
    {
        $id = intval($id);
        $result = DistrictModel::destroy($id);
        if(!$result)
        {
            return response()->json(['errCode'=>0,'errMsg'=>'删除失败！']);
        }
        //更新地区缓存
        DistrictModel::refreshAreaCache();
        return response()->json(['errCode'=>1,'id'=>$id]);
    }

    /**
     * 创建和修改数据
     * @param Request $request
     */
    public function areaCreate(Request $request)
    {
        $data = $request->except('_token');
        //确定upid
        if(!empty($data['province']) && empty($data['city']))
        {
            $upid = $data['province'];
        }elseif(!empty($data['city']))
        {
            $upid = $data['city'];
        }else
        {
            $upid = 0;
        }
        //修改或者添加数据
        if(count($data['change_ids'])>0)
        {
            foreach($data['name'] as $k=>$v)
            {
                $change_ids = explode(' ',$data['change_ids']);
                if(in_array($k,$change_ids)){
                    $result = DistrictModel::where('upid',$upid)->where('id',$k)->update(['name'=>$v,'displayorder'=>$data['displayorder'][$k]]);
                    if(!$result)
                    {
                        DistrictModel::firstOrCreate(['name'=>$v,'upid'=>$upid,'displayorder'=>$data['displayorder'][$k]]);
                    }
                }
            }
            //触发area数据缓存更新
            DistrictModel::refreshAreaCache();
        }

        return redirect()->back()->with(['massage'=>'修改成功！']);
    }

    /**
     * ajax获取城市、地区数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxCity(Request $request)
    {
        $id = intval($request->get('id'));
        if(is_null($id)){
            return response()->json(['errMsg'=>'参数错误！']);
        }
        $province = DistrictModel::findTree($id);
        $area=DistrictModel::findTree($province[0]['id']);
        $data = [
            'province'=>$province,
            'area'=>$area,
            'id'=>$id
        ];
        return response()->json($data);
    }

    /**
     * ajax获取地区的数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxArea(Request $request)
    {
        $id = intval($request->get('id'));
        if(is_null($id)){
            return response()->json(['errMsg'=>'参数错误！']);
        }
        $area = DistrictModel::findTree($id);
        return response()->json($area);
    }
}
