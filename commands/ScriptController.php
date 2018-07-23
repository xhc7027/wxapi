<?php

namespace app\commands;

use app\exceptions\SystemException;
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
     *  重新发送用户信息到消息队列
     */
    public function actionRePublishAuthorizerInfo()
    {
        try {
            $count = TsMsgSupplierFounder::find()->select(['data'])->where(['status' => 0])->count();
            if ($count > 20) {
                throw new SystemException('发送用户授权信息到消息队列失败数已达到20个');
                Yii::error('发送用户授权信息到消息队列失败数已达到20个');
            }
            $data = TsMsgSupplierFounder::find()->select(['tsId', 'data'])->where(['status' => 0])->asArray()->all();
            if ($data) {
                foreach ($data as $key => $value) {
                    if (TencentQueueUtil::publishMessage(Yii::$app->params['topic']['topicAuthorizerInfo'], $value['data'])) {
                        TsMsgSupplierFounder::updateAll(['status' => 1], ['TsId' => $value['tsId']]);
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error('发送用户授权信息到消息队列失败:' . $e->getMessage());
            throw new SystemException('发送用户授权信息到消息队列失败:' . $e->getMessage());
        }

    }
}
