<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class UpdateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('shop', function (Blueprint $table){
            $table->text('nav_rules')->comment('导航配置');
            $table->string('nav_color')->comment('导航肤色');
            $table->text('banner_rules')->comment('轮播图 附件编号json串 ');
            $table->string('central_ad')->comment('中部广告');
            $table->string('footer_ad')->comment('底部广告');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
