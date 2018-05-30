<?php

namespace Idouzi\Commons;

use Yii;

/**
 * 用于发起orc图片、关键字识别
 * Class SendOrcMsg
 * @package Idouzi\Commons
 */
class SendOrcMsg
{
    /**
     * 发起活动图片识别
     * @param array $desInfo 活动详情
     * @return bool
     */
    public static function sendEventORCMsg(array $desInfo)
    {
        try {
            //获取图片路径
            $url_data = self::getDesImg($desInfo['data']);
            if (!empty($url_data)) {
                $desInfo['imgs'] = array_merge($desInfo['imgs'], $url_data);
            }
            //加入消息队列
            TencentQueueUtil::sendMessage(Yii::$app->params['ocr_queue_cmq'],json_encode($desInfo));
        } catch (\Exception $e) {
            Yii::warning('sendEventORCMsg failed :' . $e->getMessage(), __METHOD__);
            return false;
        }
        return true;
    }

    /**
     * 获取详情中的图片链接地址
     * @param string $str
     * @return mixed
     */
    private static function getDesImg(string $str)
    {
        $reg = '/<img(.+?)src="((http|https):\/\/.+?)"/is';
        $matches = array();
        preg_match_all($reg, $str, $matches);
        $data = null;
        foreach ($matches[2] as $value) {
            $data[] = $value;
        }
        return $data;
    }
}