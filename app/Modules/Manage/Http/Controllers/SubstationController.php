<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Model\SubstationModel;
use App\Modules\User\Model\DistrictModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubstationController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'substation');

    }

    /**
     * 分站点列表
     * @param Request $request
     * @return mixed
     */
    public function substationConfig(Request $request)
    {
        //查询已经设置的分站点
        $list = SubstationModel::orderBy('sort','ASC')->orderBy('created_at','DESC')->paginate(10);
        //查询所有地区信息
        $province = DistrictModel::findTree(0);
        //查询特殊城市
        $arr = ['beijingshi','tianjinshi','zhongqingshi','shanghaishi','taiwansheng','xianggang','aomen'];
        $special = DistrictModel::whereIn('spelling',$arr)->get()->toArray();
        if(!empty($special)){
            foreach($special as $k => $v){
                $specialIds[] = $v['id'];
            }
        }
        if(!empty($province)){
            foreach($province as $k => $v){
                if(!in_array($v['spelling'],$arr)){
                    $ids[] = $v['id'];
                }
            }
            if(!empty($ids)){
                $city = DistrictModel::whereIn('upid',$ids)->orWhereIn('id',$specialIds)->get()->toArray();
            }else{
                $city = [];
            }
        }else{
            $city = [];
        }

        $data = array(
            'list' => $list,
            'district' => $city

        );
        $this->theme->setTitle('分站点设置');
        return $this->theme->scope('manage.substationlist', $data)->render();
    }


    /**
     * 添加分站点
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postAdd(Request $request)
    {
        $districtId = $request->get('tags');
		if(!$districtId){
			 return redirect()->back()->with(array('message' => '请选择地区'));
		}
        //查询分站点是否已经设置
        $re = SubstationModel::where('district_id',$districtId)->first();
        if($re){
            return redirect('/manage/substationConfig')->with(array('message' => '该城市已被设置'));
        }
        //查询分站点名称
        $city = DistrictModel::where('id',$districtId)->first();
        if(strstr($city->name,'省') || strstr($city->name,'市')){
            $cityName = mb_substr($city->name,0,-1);
        }else{
            $cityName = $city->name;
        }
        $arr = array(
            'district_id' => $districtId,
            'name' => $cityName,
            'status' => 2,
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        $res = SubstationModel::create($arr);
        if($res){
            return redirect('/manage/substationConfig')->with(array('message' => '操作成功'));
        }else{
            return redirect('/manage/substationConfig')->with(array('message' => '操作失败'));
        }

    }

    /**
     * 删除分站点
     * @param Request $request
     * @param $id 分站点id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteSub(Request $request,$id)
    {
        $res = SubstationModel::where('id',$id)->delete();
        if($res){
            return redirect('/manage/substationConfig')->with(array('message' => '操作成功'));
        }else{
            return redirect('/manage/substationConfig')->with(array('message' => '操作失败'));
        }

    }

    /**
     * 保存分站点
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function editSub(Request $request)
    {
        $data = $request->except('_token');
        if(!empty($data['sort']) && is_array($data['sort'])) {
            foreach($data['sort'] as $k => $v){
                $arr = array(
                    'sort' => $v,
                    'updated_at' => date('Y-m-d H:i:s',time())
                );
                SubstationModel::where('id',$k)->update($arr);
            }
        }
        return redirect('/manage/substationConfig')->with(array('message' => '操作成功'));


    }

    /**
     * 设置或取消热门
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeSubstation(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        if(!empty($id) && !empty($status)){
            $arr = array(
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s',time())
            );
            if($status == 1){
                //查询已经设为热门的数量
                $r = SubstationModel::where('status',1)->get()->count();
                if($r >= 6){
                    $data = array(
                        'code' => 3,
                        'msg' => '最多设置6个热门分站点'
                    );
                }else{
                    $res = SubstationModel::where('id',$id)->update($arr);
                    if($res){
                        $data = array(
                            'code' => 1,
                            'msg' => '操作成功'
                        );
                    }else{
                        $data = array(
                            'code' => 0,
                            'msg' => '操作失败'
                        );
                    }
                }

            }else{
                $res = SubstationModel::where('id',$id)->update($arr);
                if($res){
                    $data = array(
                        'code' => 1,
                        'msg' => '操作成功'
                    );
                }else{
                    $data = array(
                        'code' => 0,
                        'msg' => '操作失败'
                    );
                }
            }

        }else{
            $data = array(
                'code' => 2,
                'msg' => '缺少参数'
            );
        }
        return response()->json($data);

    }

}

