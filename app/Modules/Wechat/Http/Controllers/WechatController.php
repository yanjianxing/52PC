<?php

namespace App\Modules\Wechat\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Http\Request;
use EasyWeChat\Foundation\Application;
use Overtrue\LaravelWechat\CacheBridge;

class WechatController extends Controller
{
    public function wechat()
    {

        $configinfo = ConfigModel::getConfigByAlias('wechat_public');
        if($configinfo && !empty($configinfo['rule'])){
            $config = json_decode($configinfo['rule'],true);
            $config['debug'] = config('wechat-public.debug');
            $config['use_laravel_cache'] = config('wechat-public.use_laravel_cache');
            $config['log'] = config('wechat-public.log');
            $config['oauth'] = config('wechat-public.oauth');
            $wechat = new Application($config);

            if (config('wechat-public.use_laravel_cache')) {
                $wechat->cache = new CacheBridge();
            }
        }else{
            $wechat = app('wechat');
        }

//        $wechat = app('wechat');
        //微信服务端
        $wechatServer = $wechat->server;

        return $wechatServer->serve();
    }


}
