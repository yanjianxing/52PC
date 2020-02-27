<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\User\Model\PromoteModel;
use App\Modules\User\Model\PromoteTypeModel;
use Illuminate\Http\Request;
use Auth;

class PromoteController extends UserCenterController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('apple');//主题初始化
    }

    /**
     * 推广注册等功能
     * @param $param
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function promote($param)
    {
        return redirect('/register?uid=' . $param);
    }

    /**
     * 推广代码
     * @param Request $request
     * @return mixed
     */
    public function promoteUrl(Request $request)
    {
        $uid = Auth::id();
        //查询已经开启的推广类型
        $promoteType = PromoteTypeModel::where('is_open', 1)->get()->toArray();
        $type = array();
        if (!empty($promoteType)) {
            foreach ($promoteType as $k => $v) {
                $type[] = $v['type'];
            }
        } else {
            return redirect('/user/index')->with(array('message' => '推广功能已关闭'));
        }
        //创建 注册推广链接
        $registerUrl = PromoteModel::createPromoteUrl($uid);
        $data = array(
            'url' => $registerUrl,
            'promote_type' => $promoteType
        );
        $this->theme->setTitle('推广代码');
        return $this->theme->scope('user.extendcode', $data)->render();
    }

    /**
     * 推广收益
     * @param Request $request
     * @return mixed
     */
    public function promoteProfit(Request $request)
    {
        //查询已经开启的推广类型
        $promoteType = PromoteTypeModel::where('is_open', 1)->get()->toArray();
        if (empty($promoteType)) {
            return redirect('/user/index')->with(array('message' => '推广功能已关闭'));
        }
        $uid = Auth::id();
        //结算推广者赏金
        PromoteModel::settlementByUid($uid);
        $profit = PromoteModel::where('promote.from_uid', $uid)->where('promote.status', 2)
            ->leftJoin('users', 'users.id', '=', 'promote.to_uid')
            ->select('promote.*', 'users.name as to_name')
            ->orderBy('promote.updated_at', 'DESC')->paginate(10);
        $data = array(
            'profit' => $profit
        );
        $this->theme->setTitle('推广收益');

        return $this->theme->scope('user.extendprofit', $data)->render();
    }

}
