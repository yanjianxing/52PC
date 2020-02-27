<?php

namespace App\Modules\Manage\Http\Controllers\Auth;

use App\Http\Controllers\ManageController;
use App\Modules\Manage\Http\Requests\LoginRequest;
use App\Modules\Manage\Model\ManagerModel;
use Illuminate\Support\Facades\Session;
use Teepluss\Theme\Facades\Theme;
use Validator;
use Illuminate\Http\Request;

class AuthController extends ManageController
{
    //认证成功后跳转路由
    protected $redirectPath = '/manage';

    //认证失败后跳转路由
    protected $loginPath = '/manage/login';


    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {

    }

    /**
     * 后台登录页面
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getLogin()
    {
        if (ManagerModel::getManager()){
            return redirect($this->redirectPath);
        }

        $this->initTheme('managelogin');
        $this->theme->setTitle('后台登录');
        return $this->theme->scope('manage.login')->render();
    }

    /**
     * @param LoginRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postLogin(LoginRequest $request)
    {
        $loginManager=ManagerModel::checkPassword($request->get('username'), $request->get('password'));
        if(!$loginManager){
            return redirect($this->loginPath)->withInput()->withErrors(array('password'=> '请输入正确的密码'));
        }else if($loginManager =="no"){
            return redirect($this->loginPath)->withInput()->withErrors(array('username'=> '请输入正确的账号'));
        }
        if(ManagerModel::where('username',$request->get('username'))->where('status',2)->first())
                return redirect($this->loginPath)->withInput()->withErrors(array('password'=> '用户已禁用'));
        $user = ManagerModel::where('username',$request->get('username'))->first();
        ManagerModel::managerLogin($user);
        return redirect($this->redirectPath);

    }

    /**
     * 后台登出
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getLogout()
    {
        Session::forget('manager');
        return redirect($this->loginPath);
    }
}
