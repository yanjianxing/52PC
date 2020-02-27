<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Schema;


class AdStatisticTable extends Command
{
    protected $signature = 'create_ad_statistic';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'create ad_statistic by date';


    public function __construct()
    {
        parent::__construct();

        $this->nowYear = date("Y");
        $this->tables = [
            'ad_statistic_'.$this->nowYear.'1',
            'ad_statistic_'.$this->nowYear.'2',
            'ad_statistic_'.$this->nowYear.'3',
            'ad_statistic_'.$this->nowYear.'4',
            'ad_statistic_'.$this->nowYear.'5',
            'ad_statistic_'.$this->nowYear.'6',
            'ad_statistic_'.$this->nowYear.'7',
            'ad_statistic_'.$this->nowYear.'8',
            'ad_statistic_'.$this->nowYear.'9',
            'ad_statistic_'.$this->nowYear.'10',
            'ad_statistic_'.$this->nowYear.'11',
            'ad_statistic_'.$this->nowYear.'12',
        ];

    }

    public function handle()
    {
        if($this->tables){
            foreach($this->tables as $v){
                if ( ! Schema::hasTable($v)) {
                    Schema::create($v, function (Blueprint $table) {
                        $table->engine = 'InnoDB';
                        $table->increments('id')->comment('统计编号');
                        $table->integer('ad_id', false)->index()->comment('广告id');
                        $table->integer('target_id', false)->index()->comment('广告位置id');
                        $table->tinyInteger('type', false)->default(1)->comment('类型 1：曝光 2：点击');
                        $table->string('ip')->index()->comment('ip地址');
                        $table->timestamp('created_at')->nullable()->comment('创建时间');
                    });
                    $this->info($v.'创建成功');
                }else{
                    $this->info($v.'已存在');
                }
            }
        }
    }

}
