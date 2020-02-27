<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Http\Controllers\BasicController;
use App\Modules\Manage\Model\NavModel;
use Illuminate\Http\Request;
use App\Modules\Manage\Http\Requests\NavRequest;
use Illuminate\Support\Facades\Auth;


class NavController extends ManageController
{
	public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'nav');
        $this->theme->setTitle('自定义导航');

    }

    /**
     * 自定义导航列表
     * @param Request $request
     * @return mixed
     */
    public function navList(Request $request)
    {
        //查询自定义导航所有信息
        $navRes = NavModel::whereRaw('1 = 1');
        $by = $request->get('by') ? $request->get('by') : 'updated_at';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $navRes = $navRes->orderBy($by, $order)->paginate($paginate);
        $data = array(
            'nav_list' => $navRes,
            'paginate' => $paginate
        );
        return $this->theme->scope('manage.navlist', $data)->render();
    }

    /**
     * 新建添加自定义导航视图
     * @return mixed
     */
    public function addNav()
    {
        return $this->theme->scope('manage.addnav')->render();
    }

    /**
     * 添加自定义导航
     * @param NavRequest $request
     * @return mixed
     */
    public function postAddNav(NavRequest $request)
    {
        $data = $request->all();
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $data['updated_at'] = date('Y-m-d H:i:s',time());
        //添加信息
        $res = NavModel::create($data);
        if($res)
        {
            return redirect('manage/navList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 编辑自定义导航视图
     * @param $id 自定义导航id
     * @return mixed
     */
    public function editNav($id)
    {
        $id = intval($id);
        //根据id查询该导航详情
        $navInfo = NavModel::where('id',$id)->get()->toArray();
        $data = array(
            'navInfo' => $navInfo
        );
        return $this->theme->scope('manage.editnav',$data)->render();
    }

    /**
     * 编辑自定义导航
     * @param NavRequest $request
     * @return mixed
     */
    public function postEditNav(NavRequest $request)
    {
        $data = $request->all();
        $arr = array(
            'title' => $data['title'],
            'link_url' => $data['link_url'],
            'sort' => $data['sort'],
            'is_new_window' => $data['is_new_window'],
            'is_show' => $data['is_show'],
            'updated_at' => date('Y-m-d H:i:s',time())
        );
        //修改信息
        $res = NavModel::where('id',$data['id'])->update($arr);
        if($res)
        {
            return redirect('manage/navList')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 删除一个自定义导航
     * @param $id 自定义导航id
     * @return mixed
     */
    public function deleteNav($id)
    {
        $id = intval($id);
        $res = NavModel::where('id',$id)->delete();
        if(!$res)
        {
            return redirect()->to('/manage/navList')->with(array('message' => '操作失败'));
        }
        return redirect()->to('/manage/navList')->with(array('message' => '操作成功'));
    }

    /**
     * 设为首页
     * @param $id
     * @return mixed
     */
    public function isFirst($id)
    {
        $id = intval($id);
        //查询是否已经设置首页
        $navFirst = NavModel::where('is_first',1)->get()->toArray();
        if(!empty($navFirst))
        {
            $arr = array('is_first' => 0);
            $res = NavModel::where('id',$navFirst[0]['id'])->update($arr);
            if($res)
            {
                $nav = NavModel::where('id',$id)->update(array('is_first' => 1));
                if($nav)
                {
                    return redirect('/manage/navList')->with(array('message' => '操作成功'));
                }
                else
                {
                    return redirect('/manage/navList')->with(array('message' => '操作失败'));
                }
            }
            else
            {
                return redirect('/manage/navList')->with(array('message' => '操作失败'));
            }
        }
        else
        {
            $nav = NavModel::where('id',$id)->update(array('is_first' => 1));
            if($nav)
            {
                return redirect('/manage/navList')->with(array('message' => '操作成功'));
            }
            else
            {
                return redirect('/manage/navList')->with(array('message' => '操作失败'));
            }
        }
    }













}
