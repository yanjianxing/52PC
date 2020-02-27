<?php
/**
 * ------------------------
 * Created by PhpStorm.
 * ------------------------
 *
 * ------------------------
 * Author: frank
 * Date: 16-4-19
 * Desc:
 * ------------------------
 *
 */

namespace App\Modules\Bre\Http\Controllers;


use App\Http\Controllers\IndexController;
use App\Modules\Manage\Model\AgreementModel;
use Illuminate\Routing\Controller;


class AgreementController extends IndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('main');
    }

    /**
     * 协议
     * @return mixed
     */
    public function index($codeName)
    {
        //根据协议别名查询协议内容
        $agree = AgreementModel::where('code_name',$codeName)->first();
        $data = array(
            'agree' => $agree
        );
        $this->theme->setTitle($agree['name']);
        return $this->theme->scope('bre.agree',$data)->render();

    }









}