<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopTagsModel extends Model
{
    protected $table = 'tag_shop';

    public $timestamps = false;

    protected $fillable = [
        'id','cate_id', 'shop_id','type'
    ];

    /**
     * 根据店铺id查询店铺各种分类
     * @param int|array $shop_id
     * @param int $type
     * @return mixed
     */
    static public function shopTag($shop_id,$type=1)
    {
        if(is_array($shop_id)){
            $data = ShopTagsModel::whereIn('tag_shop.shop_id', $shop_id)->where('tag_shop.type', $type)->leftJoin('cate', 'cate.id', '=', 'tag_shop.cate_id')->select('tag_shop.*', 'cate.name')->get()->toArray();
        }else {
            $data = ShopTagsModel::where('tag_shop.shop_id', $shop_id)->where('tag_shop.type', $type)->leftJoin('cate', 'cate.id', '=', 'tag_shop.cate_id')->select('tag_shop.*', 'cate.name')->get()->toArray();
        }
        return $data;
    }

    static public function gettagname($shop_id,$type=1)
    {
        if(is_array($shop_id)){
            $data = ShopTagsModel::whereIn('tag_shop.shop_id', $shop_id)->where('tag_shop.type', $type)->leftJoin('cate', 'cate.id', '=', 'tag_shop.cate_id')->lists('cate.name')->toArray();
        }else {
            $data = ShopTagsModel::where('tag_shop.shop_id', $shop_id)->where('tag_shop.type', $type)->leftJoin('cate', 'cate.id', '=', 'tag_shop.cate_id')->lists('cate.name')->toArray();
        }
        return $data;
    }

}

