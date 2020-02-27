<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Http\Requests\AgreementRequest;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeachKeywordModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class AgreementController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'agreement');

    }

    /**
     * .中电快购列表
     * @param Request $request
     * @return mixed
     */
    public function zdfastbuy(Request $request)
    {
        $merge=[
            'keywords'=>$request->get('keywords'),//编号/关键词搜索
            'show_location'=>$request->get('show_location'),//位置筛选
            'start'    =>$request->get('start'),
            'end'    =>$request->get('end')
        ];
        $list=ZdfastbuyModel::getZdfastbuyModelList($merge,$paginate=10);
        $show_location=[
            1=>'中间',
            2=>'右边',
            3=>'首页中电快购中间',
            4=>'首页中电快购右侧',
        ];
        $data = [
            'list'=>$list,
            'merge'=>$merge,
            'show_location'=>$show_location,
        ];

        $this->theme->setTitle('中电快购列表');
        return $this->theme->scope('manage.zdfastbuy.zdfastbuy', $data)->render();
    }

    /**
     * .中电快购列表新增
     * @param Request $request
     * @return mixed
     */
    public function addZdfastbuy(Request $request)
    {
        $data = [];
        $this->theme->setTitle('中电快购列表页添加');
        return $this->theme->scope('manage.zdfastbuy.addzdfastbuy', $data)->render();
    }


    /**
     * .保存中电快购信息
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveZdfastbuy(Request $request)
    {
        $data = $request->except('_token','seo_laber');
        $arr = [
            'id'                    => isset($data['id']) ? $data['id'] : '',
            'name'                  => isset($data['name']) ? $data['name'] : '',
            'url'                   => isset($data['url']) ? $data['url'] : '',
            'aurl'                  => isset($data['aurl']) ? $data['aurl'] : '',
            'model'                  => isset($data['model']) ? $data['model'] : '',
            'store'                  => isset($data['store']) ? $data['store'] : '',
            'created_at'            => date('Y-m-d H:i:s',time()),
            'show_location'         => isset($data['show_location']) ? $data['show_location'] : 1,
        ];
        $res = ZdfastbuyModel::create($arr);
        if($res){
            return redirect('/manage/zdfastbuy')->with(array('message' => '操作成功'));
        }
        return redirect()->back()->with(array('message' => '操作失败'));
    }

    /**
     * .中电快购删除
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delZdfastbuy(Request $request)
    {
        $id = $request->get('id');
        if (!$id) {
            return redirect()->back()->with(['error' => '亲爱的小主，您暂无权限执行删除操作！']);
        }
        $res = ZdfastbuyModel::where('id',$id)->update(['is_del'=>1]);
        if($res){
            $data = array(
                'code' => 1,
                'msg' => '删除成功'
            );
        }else{
            $data = array(
                'code' => 0,
                'msg' => '删除失败'
            );
        }
        return response()->json($data);
    }



    /**
     * 协议列表
     * @param Request $request
     * @return mixed
     */
    public function agreementList(Request $request)
    {
        $agreement = AgreementModel::orderBy('id','ASC')->paginate(10);
        $data = array(
            'agree_list' => $agreement
        );
        $this->theme->setTitle('协议管理');
        return $this->theme->scope('manage.agreelist', $data)->render();
    }

    /**
     * .搜索关键词列表
     * @param Request $request
     * @return mixed
     */
    public function seachKeyword(Request $request)
    {
        $merge=[
            'keywords'=>$request->get('keywords'),//编号/关键词搜索
            'type'=>$request->get('type'),//搜索入口类型搜索
            'selectOne'=>$request->get('selectOne'),//创建时间和更新时间排序
            'order'=>$request->get('order'),
            'timefrom'=>$request->get('timefrom'),//创建时间和更新时间搜索
            'start'    =>$request->get('start'),
            'end'    =>$request->get('end')
        ];
        $list=SeachKeywordModel::getseachKeywordList1($merge,$paginate=50);
        $data = [
            'list'=>$list['list'],
            'merge'=>$merge,
            'counts'=>$list['count'],
        ];

        $this->theme->setTitle('搜索关键字列表');
        return $this->theme->scope('manage.seachkeyword', $data)->render();
    }


    /**
     * 添加协议视图
     * @param Request $request
     * @return mixed
     */
    public function addAgreement(Request $request)
    {
        $data = array();
        $this->theme->setTitle('协议管理');
        return $this->theme->scope('manage.addagree', $data)->render();
    }

    /**
     * 添加协议
     * @param AgreementRequest $request
     * @return mixed
     */
    public function postAddAgreement(AgreementRequest $request)
    {
        $data = $request->all();
        $data['content'] = htmlspecialchars($data['content']);
        if(mb_strlen($data['content']) > 4294967295/3){
            $error['content'] = '内容太长，建议减少上传图片';
            if (!empty($error)) {
                return redirect('/manage/editAgreement/'.$data['id'])->withErrors($error);
            }
        }
        $arr = array(
            'name' => $data['name'],
            'code_name' => $data['code_name'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s',time()),
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        //查询协议代号是否存在
        $agree = AgreementModel::where('code_name',$data['code_name'])->first();
        if($agree)
        {
            $error['code_name'] = '该协议代号已存在，请重新输入协议代号';
            if (!empty($error)) {
                return redirect('/manage/addAgreement')->withInput($request->only('name', 'content'))->withErrors($error);
            }
        }
        $res = AgreementModel::create($arr);
        if($res)
        {
            return redirect('/manage/agreementList')->with(array('message' => '操作成功'));
        }
        else
        {
            return redirect('/manage/agreementList')->with(array('message' => '操作失败'));
        }
    }


    /**
     * 编辑协议视图
     * @param Request $request
     * @param $id 协议编号
     * @return mixed
     */
    public function editAgreement(Request $request,$id)
    {
        $id = intval($id);
        $agree = AgreementModel::where('id',$id)->first();
        $data = array(
            'agree' => $agree
        );
        $this->theme->setTitle('协议管理');
        return $this->theme->scope('manage.editagree',$data)->render();
    }

    /**
     * 编辑协议
     * @param AgreementRequest $request
     * @return mixed
     */
    public function postEditAgreement(AgreementRequest $request)
    {
        $data = $request->all();
        $arr = array(
            'name' => $data['name'],
            'code_name' => $data['code_name'],
            'content' => $data['content'],
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        //查询协议代号是否存在
        $agree = AgreementModel::where('code_name',$data['code_name'])->where('id','!=',$data['id'])->first();
        if($agree)
        {
            $error['code_name'] = '该协议代号已存在，请重新输入协议代号';
            if (!empty($error)) {
                return redirect('/manage/editAgreement/'.$data['id'])->withInput($request->only('name','id', 'content'))->withErrors($error);
            }
        }
        $res = AgreementModel::where('id',$data['id'])->update($arr);
        if($res)
        {
            return redirect('/manage/agreementList')->with(array('message' => '操作成功'));
        }
        else
        {
            return redirect('/manage/agreementList')->with(array('message' => '操作失败'));
        }
    }

    /** 删除协议
     * @param $id
     * @return mixed
     */
    public function deleteAgreement($id)
    {
        $id = intval($id);
        $res = AgreementModel::where('id',$id)->delete();
        if($res)
        {
            return redirect()->to('/manage/agreementList')->with(array('message' => '操作成功'));
        }
        else
        {
            return redirect()->to('/manage/agreementList')->with(array('message' => '操作失败'));
        }
    }

    //模板管理
    public function skin()
    {
        $skin_color_config = \CommonClass::getConfig('skin_color_config');
        //自动获取模板
        $path = public_path().'/themes';
        $themes = \CommonClass::listDir($path);
        //获取当前模板
        $theme_now = \CommonClass::getConfig('theme');

        $view = [
            'skin_config'=>$skin_color_config,
            'themes'=>$themes,
            'theme_now'=>$theme_now
        ];
        $this->theme->setTitle('模板管理');
        return $this->theme->scope('manage.skin',$view)->render();
    }
    //模板设置
    public function skinSet($color)
    {
        if(!in_array($color,['blue','red','gray','orange']))
        {
            return redirect('manage/skin')->with(['error'=>'参数错误']);
        }
        $result = ConfigModel::where('alias','skin_color_config')->update(['rule'=>$color]);
        if(!$result)
            return redirect('manage/skin')->with(['error'=>'设置失败']);

        return redirect('manage/skin')->with(['message'=>'设置成功！']);
    }

    public function skinChange($name)
    {
        $result = ConfigModel::where('alias','theme')->update(['rule'=>$name]);

        if(!$result)
            return redirect()->back()->with(['error'=>'模板选择失败！']);

        return redirect()->back()->with(['message'=>'模板选择成功！']);
    }
}

