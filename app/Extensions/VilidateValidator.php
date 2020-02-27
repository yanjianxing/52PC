<?php

namespace app\Extensions;

use Illuminate\Validation\Validator;


/**
 * DouyasiValidator
 * 扩展自定义验证
 *
 * @author raoyc <raoyc2009@gmail.com>
 */
class VilidateValidator extends Validator
{
    /*验证手机号是否合法，使用正则判定，可能存在遗漏*/
    public function validateMobilePhone($attribute, $value ,$parameters)
    {
        return preg_match('/^(13[0-9]|15[012356789]|17[0678]|18[0-9]|14[57])[0-9]{8}$/', $value);
    }
    //验证字符串最大值
    public function validateStrLength($attribute,$value,$parameters)
    {
        if(strlen($value)>$parameters[0])
        {
            return false;
        }
        return true;
    }
    //验证正整数
    public function validatePositive($attribute,$value,$parameters)
    {
        return preg_match('/^[1-9]\d*$/',$value);
    }
    //验证两位小数
    public function validateDecimal($attribute,$value,$parameters)
    {
        return preg_match('/^[0-9]+(.[0-9]{1,2})?$/',$value);
    }
    /*验证金额不能为负数*/
    public function validatePrice($attribute, $value ,$parameters)
    {
        return preg_match('/^\\d+$/', $value);
    }
    //比较值的大小
    public function validateBountyMin($attribute,$value,$parameters)
    {
        $task_bounty_min_limit = \CommonClass::getConfig('task_bounty_min_limit');
        if($value< $task_bounty_min_limit)
        {
            return false;
        }
        return true;
    }

    public function validateBountyMax($attribute,$value,$parameters)
    {
        $task_bounty_max_limit = \CommonClass::getConfig('task_bounty_max_limit');
        if(intval($value)>$parameters &&  $task_bounty_max_limit!=0)
        {
            return false;
        }
        return true;
    }

    /**
     * 验证开始时间不能小于今天
     * @param $attribute
     * @param $value
     * @param $parameters
     */
    public function validateBeginAt($attribute,$value,$parameters)
    {
        if(strtotime(preg_replace('/([\x80-\xff]*)/i', '', $value))>=strtotime(date('Y-m-d',time())))
        {
            return true;
        }

        return false;
    }

    /**
     * 验证发布悬赏任务的截止时间合法性
     * @param $attribute
     * @param $value
     * @param $parameters
     * @return bool
     */
    public function validateDeliveryDeadline($attribute,$value,$parameters)
    {
        $bounty = json_decode($parameters[0],true);
        $begin_at = json_decode($parameters[1],true);
        //验证时间的正确性
        $task_delivery_limit_time = \CommonClass::getConfig('task_delivery_limit_time');
        $task_delivery_limit_time = json_decode($task_delivery_limit_time, true);
        $task_delivery_limit_time_key = array_keys($task_delivery_limit_time);
        $task_delivery_limit_time_key = \CommonClass::get_rand($task_delivery_limit_time_key, $bounty['bountyxuanshang']);

        if(in_array($task_delivery_limit_time_key,array_keys($task_delivery_limit_time))){
            $task_delivery_limit_time = $task_delivery_limit_time[$task_delivery_limit_time_key];
        }else{
            $task_delivery_limit_time = 100;
        }

        //验证时间的正确性
        //验证结束时间是否合法
        $delivery_deadline = strtotime(preg_replace('/([\x80-\xff]*)/i', '', $value));
        $task_delivery_limit_time = $task_delivery_limit_time * 24 * 3600;
        $begin_at = strtotime(preg_replace('/([\x80-\xff]*)/i', '', $begin_at['begin_atxuanshang']));
        //验证截稿时间不能小于开始时间
        if ($begin_at > $delivery_deadline) {
            return false;
        }
        if (($begin_at + $task_delivery_limit_time) < $delivery_deadline) {
            return false;
        }
        return true;
    }

    /**
     * 验证字符串的长度范围
     * @param $attribute
     * @param $value
     * @param $parameters
     */
    public function validateStrLengthBetween($attribute,$value,$parameters)
    {
        $str_length = mb_strlen($value);
        if($str_length<$parameters[0] || $str_length>$parameters[1])
        {
            return false;
        }

        return true;
    }

    /**
     * 验证截止时间
     * @param $attribute
     * @param $value
     * @param $parameters
     */
    public function validateDeadline($attribute,$value,$parameters)
    {
        $value_time = strtotime(preg_replace('/([\x80-\xff]*)/i', '', $value));
        $parameters_time = strtotime(date('Y-m-d',$parameters[0]));
        if($parameters_time>=$value_time)
        {
            return false;
        }
        return true;
    }

    /**
     * 验证招标任务截止时间合法性
     * @param $attribute
     * @param $value
     * @param $parameters
     * @return bool
     */
    public function validateDeliveryDeadlineBid($attribute,$value,$parameters)
    {
        $begin_at = json_decode($parameters[0],true);
        //任务交稿截止最大天数
        $task_delivery_limit_time = \CommonClass::getConfig('bid_delivery_max');
        //验证结束时间是否合法
        $delivery_deadline = strtotime(preg_replace('/([\x80-\xff]*)/i', '', $value));
        $task_delivery_limit_time = $task_delivery_limit_time * 24 * 3600;
        $begin_at = strtotime(preg_replace('/([\x80-\xff]*)/i', '', $begin_at['begin_atzhaobiao']));
        //验证截稿时间不能小于开始时间
        if ($begin_at > $delivery_deadline) {
            return false;
        }
        if (($begin_at + $task_delivery_limit_time) < $delivery_deadline) {
            return false;
        }
        return true;
    }
}
