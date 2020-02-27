<?php
/**
 * Created by PhpStorm.
 * User: kuke
 * Date: 2017/4/25
 * Time: 13:47
 */

namespace App\Contracts;


interface SmsContract
{

    public function send($mobile);

}