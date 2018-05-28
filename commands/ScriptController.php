<?php

namespace app\commands;

use app\models\AppInfo;
use app\services\migration\DeletedData;
use app\services\migration\Detector;
use yii;
use yii\console\Controller;
use yii\db\Query;
use app\models\TsMsgSupplierFounder;
use Idouzi\Commons\QCloud\TencentQueueUtil;

/**
 * 数据同步脚本控制器
 * 使用方法：在项目根目录下面执行
 * <code>
 * ./yii script
 * //或者是具体的某个动作
 * ./yii script index
 * </code>
 * @author tianmingxing
 */
class ScriptController extends Controller
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
        echo "=============================\n";
        echo "你可以在这里创建新的脚本任务，目前所支持的脚本有：\n";
        echo "./yii script/index 缺省处理\n";
        echo "./yii script/log-migration 日志迁移\n";
        echo "./yii script/upload-image 更新公众号的二维码路径\n";
        echo "=============================\n";
    }

    /**
     * 日志迁移<br>
     *
     * 每天凌晨1点执行
     */
    public function actionLogMigration()
    {
        $builder = new DeletedData();
        (new Detector($builder))->run();
    }

    /**
     * 更新公众号的二维码
     */
    public function actionUploadImage()
    {
        $saveCounter = $iterateCounter = 0;
        //批量查询已经绑定的公众号
        $query = (new Query())
            ->select(['qrcodeUrl', 'appId'])
            ->from('app_info')
            ->where(['and', 'infoType!="unauthorized"']);
        //分批循环处理
        foreach ($query->each() as $row) {
            if (strpos(trim($row['qrcodeUrl']), "http://weixinapi") === 0) {//如果已经上传过的则跳过
                continue;
            }
            //将图片下载并上传到万象优图
            $newPath = (new AppInfo())->uploadImage(trim($row['qrcodeUrl']));
            echo $newPath;
            if ($newPath) {
                //将数据插入到临时表
                Yii::$app->db->createCommand()->update('app_info',
                    ['qrcodeUrl' => strval($newPath)],
                    ['appId' => strval($row['appId'])]
                )->execute();

                $saveCounter++;
            }
            $iterateCounter++;
        }

        echo "遍历数据{$iterateCounter}条，保存数据{$saveCounter}条。\n";
    }

    /**
     * 不断发送公众号换绑事务消息
     */
    public function actionTsMsgSupplierFounder()
    {
        $queueName = Yii::$app->params['queueNames']['queueMallSupplierFounderTsMsg'];
        try {
            $sql = 'SELECT `tsId`,`data` FROM `ts_msg_supplier_founder` limit 5';
            $tsMsgData = TsMsgSupplierFounder::findBySql($sql)->asArray()->all();
            if (!$tsMsgData) return;
            $queueData = [];//发送到消息队列的数据
            foreach ($tsMsgData as $k => $v) {
                $Reconstruction = [];
                $Reconstruction = json_decode($v['data'], true);
                $Reconstruction['tsId'] = $v['tsId'];
                $queueData[] = json_encode($Reconstruction);
            }
            TencentQueueUtil::batchSendMessage($queueName, $queueData);
        } catch (\Exception $e) {
            Yii::warning('不断发送公众号换绑事务消息失败' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * 删除公众号换绑消息
     */
    public function actionDeleteSupplierFounderTsMsg()
    {
        $queueName = Yii::$app->params['queueNames']['queueWxApiDeleteFounderTsMsg'];
        try {
            $queues = TencentQueueUtil::receiveMessage($queueName);
            if (!$queues) {
                return;
            }
            if (!isset($queues->code) || $queues->code !== 0) {
                TencentQueueUtil::deleteMessage($queueName, $queues->receiptHandle);
                return;
            }
            $tsId = json_decode($queues->msgBody, true);

            TsMsgSupplierFounder::deteleData($tsId);
            TencentQueueUtil::deleteMessage($queueName, $queues->receiptHandle);

        } catch (\Exception $e) {
            Yii::warning('删除公众号换绑消息' . $e->getMessage(), __METHOD__);
        }

    }
}
