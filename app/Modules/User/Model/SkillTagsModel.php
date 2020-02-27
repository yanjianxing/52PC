<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/7/12
 * Time: 16:08
 */
namespace App\Modules\User\Model;
use Illuminate\Database\Eloquent\Model;

class SkillTagsModel extends Model
{
    protected $table = 'skill_tags';

    public $timestamps = false;

    protected $fillable = [
        'id', 'tag_name','cate_id'
    ];


}