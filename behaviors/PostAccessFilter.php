<?php

namespace app\behaviors;

use app\commons\SecurityUtil;
use app\exceptions\SystemException;
use Yii;
use yii\base\Behavior;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;

/**
 * <p>接口安全验证过虑器</p>
 *
 * 调用接口时会有两种情况：
 * 1. 当前登录用户进行接口调用，这类是允许的。
 * 2. 非登录用户（其它业务系统）调用接口时，为保证安全需要对这些接口作安全校验。因为这类接口调用通常是在页面上直接调用过来的，如果是在后台业务系统
 * 间相互调用，通过RPC的方式即可。
 *
 * @package app\controllers\filters
 */
class PostAccessFilter extends Behavior
{
    /**
     * @var array 在里面的action将做接口检验
     */
    public $actions = [];

    public function events()
    {
        return [Controller::EVENT_BEFORE_ACTION => 'beforeAction'];
    }

    /**
     * <p>请求前置拦截</p>
     *
     * @param \yii\base\ActionEvent $event
     * @throws \yii\web\ForbiddenHttpException
     * @return boolean
     */
    public function beforeAction($event)
    {   
        $actionId = $event->action->id;
        if (in_array($actionId, $this->actions)) {
            try {
                $dataArr = Yii::$app->request->post();
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