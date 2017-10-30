<?php

namespace app\controllers;

use app\services\RpcService;
use Yii;
use yii\rest\Controller;

/**
 * <p>面向业务系统的接口，前端业务之间的交互请走API接口。<p>
 * <p>1. 调用必须走内网，在配置文件中设置白名单IP</p>
 * <p>2. 不用做接口验签</p>
 *
 * <h3>其它客户端使用/h3>
 * <code>
 * <?php
 * public function actionIndex()
 * {
 *     $client = new \yar_client('http://example.idouzi.com/rpc');
 *     $client->index();
 * }
 * </code>
 * @package app\controllers
 */
class RpcController extends Controller
{
    /**
     * 接口文档权限
     *
     * @param \yii\base\Action $action
     * @return bool
     */
    public function beforeAction($action)
    {
        return parent::beforeAction($action) && $this->checkAccess();
    }

    /**
     * @return boolean 从IP判断当前访问者是否能发起本次请求
     */
    private function checkAccess()
    {
        //在开发环境不进行白名单验证
        if ('dev' == YII_ENV) {
            return true;
        }

        $ip = Yii::$app->getRequest()->getUserIP();
        foreach (Yii::$app->params['rpc']['allowedIPs'] as $filter) {
            if ($filter === '*' || $filter === $ip || (($pos = strpos($filter, '*')) !== false
                    && !strncmp($ip, $filter, $pos))
            ) {
                return true;
            }
        }

        Yii::warning('不被允许的客户端(' . $ip . ')访问', __METHOD__);

        return false;
    }

    /**
     * 服务的总入口
     */
    public function actionIndex()
    {
        $server = new \Yar_Server(new RpcService());//接口实现类
        $server->handle();
    }
}