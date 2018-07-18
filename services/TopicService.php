<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/11 0011
 * Time: 15:23
 */

namespace app\services;

use app\models\TopicMsg;
use app\models\TsMsgSupplierFounder;
use Idouzi\Commons\QCloud\TencentQueueUtil;

class TopicService
{
    /**
     * 将用户信息发送到消息队列
     * @param $id
     * @param $data
     * @return bool
     */
    public static function publish($id, $data)
    {
        if (TencentQueueUtil::publishMessage(Yii::$app->params['topic']['topicAuthorizerInfo'], $data)) {
            TsMsgSupplierFounder::updateAll(['status' => 1], ['TsId' => $id]);
        }
        return true;
    }

    /**
     * 将微信回调数据保存到数据库
     * @param $queueData
     * @param $type
     */
    public static function insertData($queueData, $type)
    {
        $topMsg = new TopicMsg();
        $topMsg->type = $type;
        $topMsg->data = $queueData;
        $topMsg->createAt = date('Y-m-d H:i:s', time());
        $data = [
            'type' => $topMsg->type,
            'data' => $topMsg->data,
            'createAt' => $topMsg->createAt
        ];
        $id = (new TsMsgSupplierFounder())->insertData(json_encode($data));
        if (!$id) {
            Yii::error('用户解绑数据插入到数据库失败' . json_encode($data));
        }
        //发送数据到消息队列
        if (!self::publish($id, $data)) {
            Yii::error('用户发送消息队列失败' . json_encode($data));
        }
    }
}