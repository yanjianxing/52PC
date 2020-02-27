<?php

namespace App\Modules\User\Model;
use App\Modules\Task\Model\TaskCateModel;
use Illuminate\Database\Eloquent\Model;
use Cache;

class TagsModel extends Model
{
    protected $table = 'skill_tags';

    public $timestamps = false;

    protected $fillable = [
        'tag_name', 'cate_id'
    ];

    /**
     *查询热门标签
     */
    static function hotTag($num)
    {
        $data = TagsModel::findAll();
        //随机取得$num个标签
        $result = array_rand($data, $num);
        $return = array();
        foreach($result as $v){
            $return[] = $data[$v];
        }
        return $return;
    }

    /**
     * 用户添加自己的标签
     * @param $data
     * @param $uid
     * @return bool
     */
    static function updateTag($data,$uid)
    {
        $result = TagsModel::firstOrCreate(['tag_name'=>$data['tag_name']])->save();
        $tag_id = TagsModel::where(['tag_name'=>$data['tag_name']])->first();
        if(!$result) return false;
        //是否需要限制用户添加标签的个数
        $result2 = UserTagsModel::updateTag($tag_id['id'],$uid);
        if($result2){
            return $tag_id['id'];
        }
        return false;
    }
    //刷新tags缓存数据
    static function betteringCache()
    {
        $tags_data = TagsModel::all()->toArray();
        //将数据缓存到文件缓存
        Cache::forever('tags_list',$tags_data);
    }

    static function findAll()
    {
        if(Cache::has('tags_list'))
        {
            $tags_data = Cache::get('tags_list');
        }else{
            $tags_data = self::all()->toArray();
            Cache::put('tags_list',$tags_data,24*60);
        }
        return $tags_data;
    }
    static function findAllSkill()
    {
        $catgory_ids = TaskCateModel::lists('id');
        $skill = TagsModel::whereIn('cate_id',$catgory_ids)->get();
        return $skill;
    }

    /**
     * @param $id
     * @param null $filds
     * @return array
     */
    static function findById($id,$filds=null)
    {
        $tags_data = self::findAll();
        $data = array();
        foreach($tags_data as $k=>$v)
        {
            if(is_array($id) && in_array($v['id'],$id))
            {
                if(is_null($filds))
                {
                    $data[] = $v;
                }elseif(is_string($filds))
                {
                    $data[] = $v[$filds];
                }elseif(is_array($filds))
                {
                    $seed = array();
                    foreach($filds as $key=>$value)
                    {
                        if(isset($v[$value]))
                        {
                            $seed[$value] = $v[$value];
                        }
                    }
                    $data[] = $seed;
                }
            }elseif($v['id']==$id)
            {
                if(is_null($filds))
                {
                    $data[] = $v;
                }elseif(is_string($filds))
                {
                    $seed[$filds] = $v[$filds];
                    $data[] = $seed;
                }elseif(is_array($filds))
                {
                    $seed = array();
                    foreach($filds as $key=>$value)
                    {
                        if(isset($v[$value]))
                        {
                            $seed[$value] = $v[$value];
                        }
                    }
                    $data[] = $seed;
                }
            }
        }
        return $data;
    }
    /**
     * 获取用户的标签
     * @param $uid
     * @return mixed
     */
    static function getUserTags($uid)
    {
        $tags = UserTagsModel::where('uid',$uid)->lists('tag_id');
        $tags_ids = array_flatten($tags);
        $tags_data = self::findById($tags_ids);

        return $tags_data;
    }
}