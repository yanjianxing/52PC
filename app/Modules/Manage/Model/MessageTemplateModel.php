<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class MessageTemplateModel extends Model
{
    //
    protected $table = 'message_template';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id','code_name','name','content','message_type','is_open','is_on_site','is_send_email','created_at','updated_at','is_send_mobile','code_mobile','mobile_code_content'
    ];

    public $timestamps = false;

    /**
     * 发送信息
     * @param string $codeName 模板代号
     * @param array $messageVariableArr 替换消息模板中变量的新数组
     * @param $sendWay 1是站内信 2是email
     * @return mixed 信息内容
     */
    static function sendMessage($codeName,$messageVariableArr,$sendWay=1)
    {
        switch($sendWay){
            case 1:
                $sendWay = 'is_on_site';
                break;
            case 2:
                $sendWay = 'is_send_email';
                break;
        }

        //查询要发送消息
        $message = MessageTemplateModel::where('code_name',$codeName)->where('is_open',1)->where($sendWay,1)->first();
        if($message['num'] > 0  && !empty($messageVariableArr))
        {
            $rule = "/\{\{[\\w](.*?)\}\}/";
            preg_match_all($rule,$message['content'],$matches);
            $oldArr = empty($matches[0])?:array_unique($matches[0]);
            $res = str_replace($oldArr,$messageVariableArr,$message['content']);
        }
        else
        {
            $res = $message['content'];
        }
        return $res;
    }


}
