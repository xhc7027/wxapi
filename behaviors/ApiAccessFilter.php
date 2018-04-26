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
class ApiAccessFilter extends Behavior
{
    /**
     * @var array 在里面的action将不会校验用户编号是否存在，仍然会做接口检验。
     */
    public $notVerifyUserActions = [];

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
     * 素材资源是跟商家用户关联的，这意味着每个资源都有对应的所属，在调用接口时必须获取到是谁（商家）在操作。
     *
     * @param \yii\base\ActionEvent $event
     * @throws \yii\web\ForbiddenHttpException
     * @return boolean
     */
    public function beforeAction($event)
    {
        try {
            //排队不做接口认证的Action
            if (in_array($event->action->id, $this->actions)) {
                //从会话中获取商家信息，只要有商家编号即可
                $supplierId = Yii::$app->session->get(Yii::$app->params['constant']['sessionName']['supplierId']);
                if ($supplierId) {//不需要走接口认证流程，直接放行即可。
                    $event->isValid = true;
                    return $event->isValid;
                } else {//如果当前用户没有登录，则必须进行接口认证。
                    $dataArr = Yii::$app->request->get();
                    unset($dataArr['r']);
                    $security = new SecurityUtil($dataArr, Yii::$app->params['publicKeys']['wxapi']);
                    $event->isValid = $security->signVerification();

                    //接口认证通过后，还需要判断是否有商家用户的编号
                    if (!in_array($event->action->id, $this->notVerifyUserActions)) {
                        $supplierId = Yii::$app->request->get('supplierId');
                        if ($supplierId) {
                            Yii::$app->session->set(Yii::$app->params['constant']['sessionName']['supplierId'], $supplierId);
                        } else {
                            throw new SystemException('参数错误');
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $event->isValid = false;
            throw new ForbiddenHttpException($e->getMessage());
        }

        return $event->isValid;
    }
}