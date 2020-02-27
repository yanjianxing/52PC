<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpecialNewsModel extends Model
{
    //
    protected $table = 'special_news';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','special_id','logo','title','url','introduction','article_id','created_at'
    ];

    public $timestamps = false;


}
