<?php

namespace App\Extensions;
use Illuminate\Support\Facades\Blade;
/**
 * DouyasiBlade
 * 扩展Blade标签
 * 一般来说，标签都是使用正则来解析的，blade标签解析也是一样的
 *
 * @author raoyc <raoyc2009@gmail.com>
 */
class ExtendBlade
{
    public static function register()
    {
        # @break : 用于php循环中，实现break
        Blade::extend(function ($view, $compiler) {
            $pattern = self::createPlainMatcher('break');
            return preg_replace($pattern, '$1<?php break; ?>$2', $view);
        });

    }
    public static function createPlainMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*)/';
    }

}
