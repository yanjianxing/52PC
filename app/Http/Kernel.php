<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

        //RBAC middleware
        'role' => \Zizaco\Entrust\Middleware\EntrustRole::class,
        'permission' => \Zizaco\Entrust\Middleware\EntrustPermission::class,
        'ability' => \Zizaco\Entrust\Middleware\EntrustAbility::class,

        'manageauth' => \App\Http\Middleware\ManageAuth::class,
        'ruleengine' => \App\Http\Middleware\RuleEngine::class,

        'RolePermission' => \App\Http\Middleware\RolePermission::class,
        'systemlog' => \App\Http\Middleware\SystemLog::class,//系统日志
        'web.auth' => \App\Http\Middleware\WebAuth::class,   //手机端token验证

        //wechat 公众号中间件
        'wechat.oauth' => \Overtrue\LaravelWechat\Middleware\OAuthAuthenticate::class,

        'substation' => \App\Http\Middleware\Substation::class,//分站的地区id验证

    ];
}
