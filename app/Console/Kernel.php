<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\Inspire::class,

        \App\Console\Commands\TaskWork::class,
        \App\Console\Commands\TaskNotice::class,
        \App\Console\Commands\TaskNoStick::class,

        \App\Console\Commands\UserStatus::class,
        \App\Console\Commands\UserStatistic::class,

        \App\Console\Commands\VipUser::class,
        \App\Console\Commands\VipUserRight::class,
        \App\Console\Commands\VipUserOldDeal::class,
        \App\Console\Commands\Coupon::class,
        \App\Console\Commands\UserCoupon::class,
        \App\Console\Commands\UserRelevant::class,
        \App\Console\Commands\VipRenew::class,

        \App\Console\Commands\AdStatisticTable::class,//广告统计表生成
        \App\Console\Commands\SeachStatisticTable::class,//关键词表生成

        //数据迁移命令
        \App\Console\Commands\FawMigrateCate::class,//cate
        \App\Console\Commands\FawMigrateUserFinance::class,//用户流水
        \App\Console\Commands\FawMigrateRealnameAuth::class,//实名认证
        \App\Console\Commands\FawMigrateEnterpriseAuth::class,//企业认证
        \App\Console\Commands\FawMigrateAlipayAuth::class,//支付宝认证

        \App\Console\Commands\FawMigrateTask::class,//竞标任务
        \App\Console\Commands\FawMigrateWork::class,//竞标任务服务商

        //Install kppw
        /*\App\Modules\Install\Console\Commands\InstallKPPW::class,*/

        //迁移用户数据
        \App\Console\Commands\MigrationUser::class,//用户信息
        \App\Console\Commands\MigrationVipCard::class,//vip卡片
        \App\Console\Commands\MigrationVipPay::class,//vip购买
        \App\Console\Commands\MigrationGoods::class,//方案
        \App\Console\Commands\MigrationArticel::class,//咨询
		\App\Console\Commands\MigrationKnowledge::class,//知识百科
        \App\Console\Commands\MigrationSpecial::class,//专题
        \App\Console\Commands\MigrationCountData::class,//数据处理用户的发布任务的个数。。。。
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('VipUser')->everyMinute();
        $schedule->command('taskWork')->everyMinute();
        $schedule->command('taskNotice')->daily();
        $schedule->command('taskNoStick')->everyMinute();
        $schedule->command('userStatus')->everyMinute();
        $schedule->command('userStatistic')->daily();
        $schedule->command('VipUserRight')->daily();

        $schedule->command('Coupon')->everyMinute();
        $schedule->command('UserCoupon')->everyMinute();
        $schedule->command('UserRelevant')->daily();
        $schedule->command('VipUserOldDeal')->daily();
        $schedule->command('VipRenew')->everyMinute();

        $schedule->command('create_ad_statistic')->yearly();
        $schedule->command('create_seach_statistic')->yearly();
    }
}
