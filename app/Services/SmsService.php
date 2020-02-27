<?php
/**
 * Created by PhpStorm.
 * User: kuke
 * Date: 2017/4/25
 * Time: 13:52
 */

namespace App\Services;

use App\Contracts\SmsContract;

class SmsService implements SmsContract
{

    public $mobile;//发送手机号

    public $template = [];//短信服务商及模板编号关系数组

    public $templateData = [];//模板内容变量数组

    public $content;//短信内容

    /**
     * 发送短信
     *
     * @param $mobile
     * @return mixed
     */
    public function send($mobile)
    {
        $sms = \PhpSms::make()->to($mobile)->template($this->template)
            ->data($this->templateData)->content($this->content);

        return $sms->send();
    }

    /**
     * 发送语音短信
     *
     * @param $code 语音短信编号
     * @return mixed
     */
    public function sendVoice($code)
    {
        return \PhpSms::voice($code)->to($this->mobile)->send();
    }

    /**
     * 设置手机发送号码
     *
     * @param $mobile
     * @return $this
     */
    public function setMobile($mobile)
    {
        return $this->mobile = $mobile;
    }

    /**
     * 设置短信模板
     *
     * @param $template
     * @return $this
     */
    public function setTemplate(array $template)
    {
        return $this->template = $template;
    }

    /**
     * 模板变量赋值
     *
     * @param array $templateData
     * @return $this
     */
    public function setTemplateData(array $templateData)
    {
        return $this->templateData = $templateData;
    }

    /**
     * 设置模板内容
     *
     * @param $content
     * @return $this
     */
    public function setContent($content)
    {
        return $this->content = $content;
    }

    /**
     * 初始化短信数据对象
     *
     * @param array $template
     * @param array $templateData
     * @param null $content
     * @return $this
     */
    public function init(array $template, array $templateData, $content = null)
    {
        $this->setTemplate($template);

        $this->setTemplateData($templateData);

        $this->setContent($content);

        return $this;
    }


}