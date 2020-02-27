<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InterfaceController extends ManageController
{
	public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->set('manageType', 'Interface');
    }

    /**
     * 支付配置
     *
     * @return mixed
     */
    public function getPayConfig()
    {
        $config = ConfigModel::getConfigByAlias('cash')->toArray();
        $data = array(
            'data' => json_decode($config['rule'], true)
        );
        $this->theme->setTitle('支付接口');
        return $this->theme->scope('manage.config.interface', $data)->render();
    }

    /**
     * 保存支付配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postPayConfig(Request $request)
    {
        ConfigModel::updateConfig($request->all());
        return redirect('manage/payConfig')->with(array('message' => '保存成功'));
    }

    /**
     * 第三方支付配置列表
     *
     * @return mixed
     */
    public function getThirdPay()
    {
        $config = ConfigModel::where('type', 'thirdpay')->get()->toArray();
        foreach ($config as $k => $v){
            $config[$k]['rule'] = json_decode($v['rule'], true);
        }

        $data = array(
            'data' => $config
        );
        return $this->theme->scope('manage.config.thirdpay', $data)->render();
    }

    /**
     * 启用/禁用支付接口
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function thirdPayHandle($id, $action)
    {
        $info = ConfigModel::where('id', $id)->first();
        if (!empty($info)){
            $rule = json_decode($info->rule, true);
            switch ($action){
                case 'enable':
                    $rule['status'] = 1;
                    break;
                case 'disable':
                    $rule['status'] = 0;
                    break;
            }
            $status = $info->update(array('rule' => json_encode($rule)));
            if ($status)
                Cache::forget('thirdpay');
                return redirect('manage/thirdPay')->with(array('message' => '操作成功'));
        }
    }

    /**
     * 配置支付接口视图
     *
     * @param $id
     * @return mixed
     */
    public function getThirdPayEdit($id)
    {
        $info = ConfigModel::where('id', $id)->first()->toArray();
        $info['rule'] = json_decode($info['rule'], true);

        $data = array(
            'data' => $info
        );

        return $this->theme->scope('manage.config.thirdpayedit', $data)->render();
    }

    /**
     * 保存支付配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postThirdPayEdit(Request $request)
    {
        $id = intval($request->get('id'));
        $info = ConfigModel::where('id', $id)->first();

        if (!empty($info)){
            $status = $info->update(array('rule' => json_encode($request->get('rule'))));
            if ($status)
                Cache::forget('thirdpay');
                return redirect('manage/thirdPay')->with(array('message' => '操作成功'));
        }

    }

    /**
     * 第三方登录授权配置
     *
     * @return mixed
     */
    public function getThirdLogin()
    {
        $config = ConfigModel::getConfigByType('oauth');
        $data = array(
            'data' => $config
        );
        $this->theme->setTitle('Oauth登录');
        return $this->theme->scope('manage.config.thirdlogin', $data)->render();
    }

    /**
     * 保存第三方登录配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postThirdLogin(Request $request)
    {
        $data = array(
            'qq_api' => $request->get('qq'),
            'wechat_api' => $request->get('wechat'),
            'sina_api' => $request->get('sina'),
        );
        ConfigModel::updateConfig($data);
        Cache::forget('oauth');
        return redirect('manage/thirdLogin')->with(array('message' => '操作成功'));
    }
}
