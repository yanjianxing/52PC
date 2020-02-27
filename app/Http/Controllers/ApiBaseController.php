<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ApiBaseController extends Controller
{

    public function formateResponse($code = 1000, $message = 'success', $data = null, $statusCode = 200)
    {
        $result['code'] = $code;
        $result['message'] = $message;
        if (isset($data)) {
            $result['data'] = is_array($data) ? $data : json_decode($data, true);
        } else {
            $result['data'] = new \stdClass();
        }

        // 实例化返回对象
        return new Response($result, $statusCode);
    }

    /*
     *根据新浪IP查询接口获取IP所在地
     */
    public function getIPLocSina($queryIP)
    {
        $url = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $queryIP;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
        $location = curl_exec($ch);
        $location = json_decode($location);
        curl_close($ch);
        $loc = "";
        if ($location === FALSE) return "";
        if (empty($location->desc)) {
            $loc = $location->province . '-' . $location->city;
        } else {
            $loc = $location->desc;
        }
        return $loc;
    }
}
