<?php

namespace app\commands;

use app\models\RequestLogger;
use yii;
use yii\console\Controller;

/**
 * 删除昨天请求日志
 */
class DeleteRequestLogDataController extends Controller
{
    /**
     * @var float 记录脚本开始的时间
     */
    private $timeStart = null;

    public function beforeAction($action)
    {
        $this->timeStart = time();
        Yii::trace(date('Y-m-d H:i:s', $this->timeStart) . "开始执行脚本", __METHOD__);
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        $timeEnd = time();
        $time = $timeEnd - $this->timeStart;
        Yii::trace("{$action->id}脚本执行总耗时：{$time}秒", __METHOD__);
        return parent::afterAction($action, $result);
    }

    /**
     * 默认动作
     */
    public function actionIndex()
    {
        try {
            RequestLogger::deleteAll(
                'reqTime < :time',
                [':time' => date('Y-m-d', strtotime((int)Yii::$app->params['delDataTime'] . ' day'))]
            );
        } catch (\Exception $e) {
            Yii::warning('删除请求日志数据失败,error:' . $e->getMessage(), __METHOD__);
        }

        return true;
    }
}