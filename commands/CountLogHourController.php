<?php

namespace app\commands;

use app\exceptions\ModelValidateException;
use app\services\RequestLogService;
use yii;
use yii\console\Controller;

/**
 * 请求日志数据按小时统计脚本
 */
class CountLogHourController extends Controller
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
        $dbTrans = Yii::$app->db->beginTransaction();
        try{
            $time = date('Y-m-d H', time() - 3600);
            $rows = (new yii\db\Query())
                ->select(['type','DATE(reqTime) AS reqDay' ,'HOUR(reqTime) AS reqHour', 'MIN(timeConsume) AS minTimeConsume',
                    'MAX(timeConsume) AS maxTimeConsume', 'COUNT(*) AS number'])
                ->from('request_logger')
                ->where(['and', ['>=','reqTime', $time . ':00:00'], ['<=','reqTime',$time . ':59:60']])
                ->groupBy( 'type,HOUR(reqTime)')
                ->all();
            if(!$rows) return true;
            foreach ($rows as $row){
                RequestLogService::insertRequestLogHour($row);
            }
            $dbTrans->commit();
        }catch (\Exception $e){
            $dbTrans->rollback();
            Yii::warning('按小时统计日志数据失败,error:' . $e->getMessage(), __METHOD__);
            throw new ModelValidateException('按小时统计日志数据失败');
        }

        return true;
    }
}