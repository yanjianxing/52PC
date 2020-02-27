<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class ActivityModel extends Model
{
    //
    protected $table = 'activity';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id',
        'title',
        'pic',
        'url',
        'username',
        'status',
        'type',
        'created_at',
        'updated_at',
        'pub_at',
        'desc',
        'stoptime',
    ];


    /**
     * //.通知公告
     * @param $code
     * @return bool|mixed
     */
    static public function getAnnouncementsList()
    {
        //.1、通知公告 2、未到期
        $res = ActivityModel::where('status', 1)->where('type', 2)->where('stoptime','>',date('Y-m-d H:i:s', time()))->select('id', 'title', 'url', 'type','stoptime','status')->orderBy('id','desc')->get()->toArray();
        $res = isset($res) && !empty($res) ? $res : [];
        return $res;
    }

}
