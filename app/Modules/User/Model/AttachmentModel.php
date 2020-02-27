<?php

namespace App\Modules\User\Model;
use Illuminate\Database\Eloquent\Model;

class AttachmentModel extends Model
{
    protected $table = 'attachment';

    public $timestamps = false;

    protected $fillable = [
        'name', 'type', 'size', 'url', 'status', 'user_id', 'disk', 'created_at'
    ];

    public function work()
    {
        return $this->morphedByMany('App\Modules\Task\Model\WorkModel', 'work_attachment');
    }
    /**
     * 创建一条附件记录
     */
    static function createOne($data)
    {
        $attatchment = new AttachmentModel();
        $attatchment->name = $data['name'];
        $attatchment->type = $data['type'];
        $attatchment->size = $data['size'];
        $attatchment->url = $data['url'];
        $attatchment->created_at = date('Y-m-d H:i:s',time());
        $result = $attatchment->save();
        return $result;
    }

    /**
     * 删除一条附件记录
     */
    static function del($id,$uid)
    {
        $result = UserAttachmentModel::del($uid,$id);
        if(!$result)
        {
            return false;
        }
        $result2 = Self::where('id','=',$id)->delete();
        return $result2;
    }

    /**
     * 通过id查询附件信息
     * @array $ids
     * @return mixed
     */
    static function findByIds($ids)
    {
        $data = Self::whereIn('id',$ids)->get();
        return $data;
    }

    /**
     * 检查附件是否上传成功
     * @param $ids
     */
    static function fileAble($ids)
    {
        $data = Self::select('attachment.id')->whereIn('id',$ids)->get()->toArray();
        return $data;
    }
    /**
     * 修改没有生效的附件记录
     * @param $ids
     */
    public function statusChange($ids)
    {
        $query = Self::where('status',0);
        if(is_array($ids))
        {
            $query = $query->whereIn('id',$ids);
        }else
        {
            $query = $query->where('id',$ids);
        }
        $result = $query->update(['status'=>1]);

        return $result;
    }

    /**
     * 生成后台方案封面html
     * @param $data
     * @return string
     */
    static function getAttachmentHtml($data)
    {
        $domain = \CommonClass::getDomain();
        $url = $domain.'/'.$data['url'];
        $attachmentHtml = '';
        if(count($data)){
            $attachmentHtml .= '<p class="atta-'.$data['id'].'">
            <input type="hidden" name="file_ids[]" value="'.$data['id'].'" />
            <a href="/manage/download/'.$data['id'].'" target="_blank"><img src="'.$url.'" width="100px" height="100px"></a>
            <span rel="'.$data['id'].'" onclick="delFile(this)"  data-toggle="modal" data-target="#fileDelete">删除</span> <input type="radio" name="is_default" value="'.$data["id"].'" checked="checked">设为封面
            </p>';
        }

        return $attachmentHtml;
    }
    /**
     * 生成前台方案封面html
     * @param $data
     * @return string
     */
    static public function getAttachmentGoodsCoverHtml($data)
    {
        $domain = \CommonClass::getDomain();
        $url = $domain.'/'.$data['url'];
        $attachmentHtml = '';
        if(count($data)){
            $attachmentHtml .= '<li class="atta-'.$data['id'].'">
                                    <input type="hidden" name="file_ids[]" value="'.$data['id'].'" />
                                    <img src="'.$url.'" alt="">
                                    <div class="fengImgShowRadios">
                                        <input type="radio" name="is_default" value="'.$data["id"].'" checked="checked">
                                        <span>设为封面</span>
                                        <span rel="'.$data['id'].'" onclick="deleteCover(this)" style="cursor: pointer">删除</span>
                                    </div>
                                </li>';
        }

        return $attachmentHtml;
    }

    static public function getAttachmentGoodsDocHtml($data)
    {
        $attachmentHtml = '';
        if(count($data)){
            $attachmentHtml .= '<li class="atta-'.$data['id'].'">
                                    <input type="hidden" name="GoodsDoc_ids[]" value="'.$data['id'].'" />
                                    <p>'.$data["name"].'&nbsp;&nbsp;&nbsp;<span rel="'.$data['id'].'" onclick="delFile(this)"  data-toggle="modal" data-target="#fileDelete" style="cursor: pointer;">删除</span><p>

                                </li>';
        }

        return $attachmentHtml;
    }

    /**
     * .生成后台-全局--中电快购列表页--中电快购图片上传
     * @param $data
     * @return string
     */
    static public function getAttachmentzdfastbuyHtml($data)
    {
        $domain = \CommonClass::getDomain();
        $url = $domain.'/'.$data['url'];
        $attachmentHtml = '';
        if(count($data)){
            $attachmentHtml .= '<p class="atta-'.$data['id'].'">
            <input type="hidden" name="file_ids[]" value="'.$data['id'].'" />
            <a href="/manage/download/'.$data['id'].'" target="_blank"><img src="'.$url.'" width="100px" height="100px"></a>
            <span rel="'.$data['id'].'" onclick="delFile(this)"  data-toggle="modal" data-target="#fileDelete">删除</span> <input type="radio" name="is_default" value="'.$data["id"].'" checked="checked"><input type="hidden" name="name" value="'.$data["name"].'" checked="checked"><input type="hidden" name="url" value="'.$data["url"].'" checked="checked">
            </p>';
        }

        return $attachmentHtml;
    }

    static public function getTaskAttachmentHtml($data)
    {
        $attachmentHtml = '';
        if(count($data)){
            $attachmentHtml .= '<li class="atta-'.$data['id'].'">
                                    <input type="hidden" name="file_ids[]" value="'.$data['id'].'" />
                                    <p>'.$data["name"].'&nbsp;&nbsp;&nbsp;<span rel="'.$data['id'].'" onclick="delFile(this)"  data-toggle="modal" data-target="#fileDelete" style="cursor: pointer;">删除</span><p>

                                </li>';
        }

        return $attachmentHtml;
    }
}