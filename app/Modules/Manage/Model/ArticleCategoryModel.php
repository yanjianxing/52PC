<?php

namespace  App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;

class ArticleCategoryModel extends Model
{
    //
    protected $table = 'article_category';
    protected $primaryKey = 'id';


    protected $fillable = [
        'id','pid','cate_name','articles','display_order','created_at','updated_at'
    ];

    public $timestamps = false;

    /**
     * @param array $data
     * @return mixed
     */
    static function createCategory(array $data)
    {
        //创建文章
        $now = date('Y-m-d H;i:s',time());
        $articleArr = array(
            'pid' => $data['upID'],
            'cate_name' => $data['catName'],
            'display_order' => $data['displayOrder'],
            'created_at' => $now,
            'updated_at' => $now
        );
        $res =  ArticleCategoryModel::insert($articleArr);
        return $res;
    }

    /**
     * 获取树形分类结构
     * @return array
     */
    static function getTree()
    {
        $data = ArticleCategoryModel::get()->toArray();
        return self::_reSort($data);
    }

    static function _reSort($data,$upID = 0,$level=0,$isClear=TRUE)
    {
        static $ret = array();
        if($isClear)
        {
            $ret = array();
        }
        foreach($data as $k=>$v)
        {
            if($v['pid'] == $upID)
            {
                $v['level'] = $level;
                $ret[] = $v;
                self::_reSort($data,$v['id'],$level+1,FALSE);
            }
        }
        return $ret;
    }

    static function _children($data,$upID=0,$isClear=TRUE)
    {
        static $ret = array();
        if($isClear)
        {
            $ret = array();
        }
        foreach ($data as $k=>$v)
        {
            if($v['pid'] == $upID)
            {
                $ret[] = $v['id'];
                self::_children($data,$v['id'],FALSE);
            }
        }
        return $ret;
    }

    /**
     * 获取根分类id
     */
    static function getParentId($id,$fid=0,$isClear=true)
    {
        static $pid = '';
        if($isClear){
            $pid = '';
        }
        $parentCate = ArticleCategoryModel::where('id',$id)->first();
        if($parentCate->pid != $fid){
           self::getParentId($parentCate->pid,$fid,false);
        }else{
            $pid = $parentCate->id;
        }
        return $pid;
    }
}
