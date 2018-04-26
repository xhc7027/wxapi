<?php

namespace app\behaviors;

use app\commons\SecurityUtil;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidParamException;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;

/**
 * 判断商家用户是否已经登录
 * @package app\components
 */
class SupplierAccessFilter extends Behavior
{
    /**
     * @var array 仅过滤此数组中声明的action
     */
    public $actions = [];

    public function events()
    {
        return [Controller::EVENT_BEFORE_ACTION => 'beforeAction'];
    }

    /**
     * <p>请求前置拦截</p>
     * <p>
     * 从会话中判断用户是否存在登录信息，如果有则允许访问商城，否则转发到爱豆子登录页面。
     * </p>
     * @param \yii\base\ActionEvent $event
     * @throws \yii\web\ForbiddenHttpException
     * @return boolean
     */
    public function beforeAction($event)
    {
        $actionId = $event->action->id;

        //代理平台都为get请求
        if (in_array($actionId, $this->actions)) {
            try {
                $dataArr = Yii::$app->request->get();
                unset($dataArr['r']);
                $security = new SecurityUtil($dataArr, Yii::$app->params['publicKeys']['wxapi']);
                $flag = $security->signVerification();
                if (!$flag) {
                    $event->isValid = false;
                }
            } catch (InvalidParamException $e) {
                throw new ForbiddenHttpException($e->getMessage());
            }
        }

        return $event->isValid;
    }
}