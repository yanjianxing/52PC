<?php
/**
 * Created by PhpStorm.
 * User: kuke
 * Date: 2017/4/25
 * Time: 14:07
 */
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class WeSms extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'LeeSms';
    }
}