<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/6/24
 * Time: 13:56
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Config;
use Cache;
use Illuminate\Support\Facades\Crypt;
use Log;

class WebAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        // 验证token
        if (empty($request->get('token'))) {
            return $this->formateResponse(1011,'请先登录！');
        } else {
            // 解密用户token
            $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
            if ( is_array($tokenInfo) && isset($tokenInfo['uid']) && isset($tokenInfo['name']) /*&& isset($tokenInfo['email'])*/ && isset($tokenInfo['akey']) && isset($tokenInfo['expire'])) {
                $akey = md5(Config::get('app.key'));
                // 验证用户cache是否存在以及akey是否正确
                if ( $tokenInfo['expire'] > time() && $akey == $tokenInfo['akey'] && Cache::get($tokenInfo['uid'])) {
                    return $next($request);
                } else {
                    // cache过期或者被删除重新登录
                    return $this->formateResponse(1013,'登录过期,请重新登录！');
                }
            } else {
                // 用户模拟的token无法正确解析
                return $this->formateResponse(1012,'不合法的token！');
            }
        }

    }

    /**
     * 返回值封装
     *
     * @param int $code
     * @param string $message
     * @param null $data
     * @param int $statusCode
     * @return Response
     */
    public function formateResponse($code=1000, $message='success', $data=null, $statusCode=200){
        $result['code'] = $code;
        $result['message'] = $message;
        if (isset($data)) {
            $result['data'] = is_array($data) ? $data : json_decode($data,true);
        }
        return new Response($result,$statusCode);
    }

}
