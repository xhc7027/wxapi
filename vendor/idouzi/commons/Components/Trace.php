<?php

namespace Idouzi\Commons\Components;

use Idouzi\Commons\TraceUtil;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;

/**
 * 在每个请求处理开始前初始化日志跟踪系统
 *
 * 需要在配置文件中进入如下设置：
 * bootstrap.1 = 'trace'
 * components.trace.class = 'Idouzi\Commons\Components\Trace'
 *
 * @package app\components
 */
class Trace extends Component implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_REQUEST, function () use ($app) {
            if ($app->getRequest() instanceof \yii\web\Request) {
                $route = $app->getRequest()->getAbsoluteUrl() . $app->getRequest()->getQueryString();
            } else {
                $route = json_encode($app->getRequest()->getParams());
            }
            TraceUtil::init($route);
        });

        $app->on(Application::EVENT_AFTER_REQUEST, function () use ($app) {
            TraceUtil::trace();
        });
    }

}