<?php

namespace App\Modules\Task\Model;

use Illuminate\Database\Eloquent\Model;

class TaskCateModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cate';

    protected $fillable = [
       'name','pid','sort','type','path','choose_num'
    ];

    public function parentTask()
    {
        return $this->belongsTo('App\Modules\Task\Model\TaskCateModel', 'pid', 'id');
    }
    /**
     * 关联查询
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childrenTask()
    {
        return $this->hasMany('App\Modules\Task\Model\TaskCateModel', 'pid', 'id');
    }

    /**
     * 无极分类查询,父级在上
     */
    static function findAll()
    {
        $data = Self::with('childrenTask')->where('pid','=',0)->get()->toArray();

        return $data;
    }

    /**
     * 无极分类查询,子级在上
     */
    static function parentCate()
    {
        $data = Self::with('parentTask')->get()->toArray();

        return $data;
    }

    /**
     * 通过id查询task
     * @param $id
     * @return mixed
     */
    static function findById($id)
    {
        $data = Self::where('id','=',$id)->first();

        return $data;
    }

    /**
     * 通过pid查询分类
     * @param $pid
     * author: muker（qq:372980503）
     */
    static function findByPid($pid)
    {
        return Self::where('pid','=',$pid)->get()->toArray();
    }

    /**
     * 通过pid查询所有的底层分类id
     * @param $pid
     * author: muker（qq:372980503）
     */
    static function findCateIds($pid)
    {
        return Self::where('path','like','%'.$pid.'%')->lists('id')->toArray();
    }
}
