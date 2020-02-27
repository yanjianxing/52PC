<?php

namespace App\Http\Controllers;

use App\Modules\Manage\Model\MenuModel;
use App\Modules\Manage\Model\MenuPermissionModel;
use App\Modules\Manage\Model\Permission;
use App\Modules\Manage\Model\ManagerModel;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Support\Facades\Route;
use Cache;


class ManageController extends BasicController
{
    public $manager;
    public function __construct()
    {
        parent::__construct();
        //设置主题为默认后台主题
        $this->themeName = 'admin';
        //初始化后台菜单
        if (ManagerModel::getManager())
        {
            //
            $this->manageBreadcrumb();
            $this->breadcrumb = $this->theme->breadcrumb();
            $this->manager = ManagerModel::getManager();
            $this->theme->setManager($this->manager->username);

            //初始化后台菜单
            $manageMenu = MenuModel::getMenuPermission();
            $this->theme->set('manageMenu', $manageMenu);
        }

        //路由与面包屑
        $route = Route::currentRouteName();
        //查询权限,除了登录页面的路由
        if($route!='loginCreatePage')
        {
            $permission = Permission::where('name',$route)->first();
            if(!is_null($permission))
            {
                $permission = MenuPermissionModel::where('permission_id',$permission['id'])->first();
                //查询菜单
                $menu_data = MenuModel::getMenu($permission['menu_id']);
                $this->theme->set('menu_data', $menu_data['menu_data']);
                $this->theme->set('menu_ids',$menu_data['menu_ids']);
            }
        }

        //获取基本配置（IM css自适应 客服QQ）
        $basisConfig = ConfigModel::getConfigByType('basis');
        if(!empty($basisConfig)){
            $this->theme->set('basis_config',$basisConfig);
        }

        //菜单图标(先写死)
        $menuIcon = [
            '首页'=>'fa-home',
            '全局'=>'fa-cog',
            '用户'=>'fa-users',
            '店铺'=>'fa-home',
            '任务'=>'fa-tasks',
            '工具'=>'fa-user',
            '资讯'=>'fa-file-text',
            '广告管理'=>'fa-file-text',
            '成功案例'=>'fa-file-text',
            '财务'=>'fa-bar-chart-o',
            '消息'=>'fa-envelope',
            '应用'=>'fa fa-pencil-square-o',
            'VIP管理'=>'fa fa-pencil-square-o'
        ];
        $this->theme->set('menuIcon',$menuIcon);

        //获取授权码
        $kppwAuthCode = config('kppw.kppw_auth_code');
        if(!empty($kppwAuthCode)){
            $kppwAuthCode = \CommonClass::starReplace($kppwAuthCode, 5, 4);
            $this->theme->set('kppw_auth_code',$kppwAuthCode);
        }

    }
}
