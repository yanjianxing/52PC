<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Cache;

class ConfigModel extends Model
{
    //
    protected $table = 'config';

    protected $fillable = [
        'alias', 'rule', 'type', 'title', 'desc'
    ];

    public $timestamps = false;

    /**
     * 通过别名获取配置项
     *
     * @param $alias
     * @return bool
     */
    static function getConfigByAlias($alias)
    {
        $info = ConfigModel::where('alias', $alias)->first();
        if (!empty($info)) {
            return $info;
        }
        return false;
    }

    /**
     * 通过配置项类型获取配置项
     *
     * @param $type
     * @return bool
     */
    static function getConfigByType($type)
    {
        if(Cache::get($type)){
            $info = Cache::get($type);
        }else{
            $info = ConfigModel::where('type', $type)->get()->toArray();
            Cache::put($type,$info,60*24);
        }
        if (!empty($info)) {
            foreach ($info as $k => $v) {
                if ($type == 'basic') {
                    $result[$v['alias']] = $v['rule'];
                } else {
                    if (\CommonClass::isJson($v['rule'])){
                        $result[$v['alias']] = json_decode($v['rule'], true);
                    } else {
                        $result[$v['alias']] = $v['rule'];
                    }
                }
            }
            return $result;
        }
        return false;
    }

    /**
     * 更新配置项
     *
     * @param array $data
     * @return bool
     */
    static public function updateConfig(array $data)
    {
        foreach ($data as $k => $v)
        {
            if (is_array($v)){
                $rule['rule'] = json_encode($v);
            } else {
                $rule['rule'] = $v;
            }
            self::where('alias', $k)->update($rule);
        }
    }

    /**
     * 获取支付配置规则
     *
     * @param $alias
     * @return array|mixed
     */
    static function getPayConfig($alias)
    {
        $config = ConfigModel::where('alias', $alias)->first()->toArray();
        if (!empty($config)){
            $config['rule'] = json_decode($config['rule'], true);
            switch ($alias){
                case 'alipay':
                    $config['rule'] = array(
                        'sellerEmail' => $config['rule']['sellerEmail'] ? $config['rule']['sellerEmail'] : Config::get('laravel-omnipay.gateways.alipay.options.sellerEmail'),
                        'partner' => $config['rule']['partner'] ? $config['rule']['partner'] : Config::get('laravel-omnipay.gateways.alipay.options.partner'),
                        'key' => $config['rule']['key'] ? $config['rule']['key'] : Config::get('laravel-omnipay.gateways.alipay.options.key'),
                    );
                    break;
                case 'wechatpay':
                    $config['rule'] = array(
                        'appId' => $config['rule']['appId'] ? $config['rule']['appId'] : Config::get('laravel-omnipay.gateways.wechat.options.appId'),
                        'appKey' => $config['rule']['appKey'] ? $config['rule']['appKey'] : Config::get('laravel-omnipay.gateways.wechat.options.appKey'),
                        'mchId' => $config['rule']['mchId'] ? $config['rule']['mchId'] : Config::get('laravel-omnipay.gateways.wechat.options.mchId'),
                    );
                    break;
                case 'unionpay':
                    $config['rule'] = array(
                        'merId' => $config['rule']['merId'] ? $config['rule']['merId'] : Config::get('laravel-omnipay.gateways.unionpay.options.merId'),
                        'certPassword' => $config['rule']['certPassword'] ? $config['rule']['certPassword'] : Config::get('laravel-omnipay.gateways.unionpay.options.certPassword'),
                    );
                    break;
            }
            return $config['rule'];
        }
    }

    /**
     * 获取第三方授权配置项
     *
     * @param $alias
     * @return array|mixed
     */
    static function getOauthConfig($alias)
    {
        $config = ConfigModel::where('alias', $alias)->first()->toArray();
        if (!empty($config)){
            $config['rule'] = json_decode($config['rule'], true);
            switch ($alias){
                case 'qq_api':
                    $config['rule'] = array(
                        'appId' => $config['rule']['appId'] ? $config['rule']['appId'] : Config::get('services.qq.client_id'),
                        'appSecret' => $config['rule']['appSecret'] ? $config['rule']['appSecret'] : Config::get('services.qq.client_secret'),
                        'redirect' => Config::get('services.qq.redirect') ? Config::get('services.qq.redirect') : url('oauth/qq/callback')
                    );
                    break;
                case 'sina_api':
                    $config['rule'] = array(
                        'appId' => $config['rule']['appId'] ? $config['rule']['appId'] : Config::get('services.weibo.client_id'),
                        'appSecret' => $config['rule']['appSecret'] ? $config['rule']['appSecret'] : Config::get('services.weibo.client_secret'),
                        'redirect' => Config::get('services.weibo.redirect') ? Config::get('services.weibo.redirect') : url('oauth/weibo/callback')
                    );
                    break;
                case 'wechat_api':
                    $config['rule'] = array(
                        'appId' => $config['rule']['appId'] ? $config['rule']['appId'] : Config::get('services.weixinweb.client_id'),
                        'appSecret' => $config['rule']['appSecret'] ? $config['rule']['appSecret'] : Config::get('services.weixinweb.client_secret'),
                        'redirect' => Config::get('services.weixinweb.redirect') ? Config::get('services.weixinweb.redirect') : url('oauth/weixinweb/callback')
                    );
                    break;
            }
            return $config['rule'];
        }
    }


    /**
     * 获取vip首页的配置信息
     *
     * @param $type
     * @return bool
     */
    static function getVipConfigByType($type)
    {
        if(Cache::get($type)){
            $info = Cache::get($type);
        }else{
            $info = ConfigModel::where('type', $type)->first();
            Cache::put($type,$info,60*24);
        }
        if (!empty($info)) {
            if (\CommonClass::isJson($info['rule'])){
                $result = json_decode($info['rule'], true);
            } else {
                $result = $info['rule'];
            }
            return $result;
        }
        return false;
    }

    /**
     * 获取短信发送配置信息
     * @param $alias
     * @return array|bool|mixed|string
     */
    static public function phpSmsConfig($alias)
    {
        $arr = [
            'phpsms_scheme',
            'phpsms_config',
            'sendMobileCode',
            'sendMobilePasswordCode',
            'sendBindSms',
            'sendUnbindSms',
            'sendTaskCode',
            'sendUserPassword',
			'sendLoinPassword',
        ];
        if(!in_array($alias,$arr)){
            return false;
        }
        $config = ConfigModel::getConfigByAlias($alias);
        switch($alias){
            case 'phpsms_scheme':
                if($config && !empty($config['rule'])){
                    $rule = $config['rule'];
                }else{
                    $scheme = config('phpsms.scheme');
                    $rule = (!empty($scheme) && is_array($scheme)) ? $scheme[0] : '';
                }
                break;
            case 'phpsms_config':
                $rule = [];
                if($config && !empty($config['rule'])){
                    $rule = json_decode($config['rule'],true);
                }
                break;
            case 'sendMobileCode':
            case 'sendMobilePasswordCode':
            case 'sendBindSms':
            case 'sendUnbindSms':
            case 'sendTaskCode':
            case 'sendUserPassword':
			case 'sendLoinPassword':
            $rule = '';
            if($config && !empty($config['rule'])){
                $rule = $config['rule'];
            }
                break;

        }
        return $rule;

    }

    /**
     * 判断是否开启kee
     * @return bool
     */
    static public function isOpenKee()
    {
        $isKee = false;
        //查询接入交付台并开启该功能
        $config = ConfigModel::getConfigByAlias('kee_key');
        if($config && !empty($config['rule'])){
            $isOpen = ConfigModel::getConfigByAlias('open_kee');
            if($isOpen && $isOpen['rule'] == 1){
                $isKee = true;
            }
        }
        return $isKee;
    }
}
