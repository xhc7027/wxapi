<?php

namespace Idouzi\Commons\QCloud;

use Idouzi\Commons\Models\RespMsg;
use Yii;

/**
 * 用于处理上传到万象优图的模块
 * Class TencentPicUtil
 * @package Idouzi\Commons
 */
class TencentPicUtil
{
    /**
     * cos签名获取，用于万象优图上传的sign生成
     **/
    public static function getCosSign()
    {
        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        $file = date("YmdHis") . '_' . rand(10000, 99999);
        $bucket = \Tencentyun\Conf::BUCKET; // 自定义空间名称，在http://console.qcloud.com/image/bucket创建
        $expired = time() + 600; //过期时间
        $sign_cache = "cos_img_sign";
        $sign = Yii::$app->cache->get($sign_cache);
        $url = \Tencentyun\ImageV2::generateResUrl($bucket, 0);
        if (empty($sign)) {
            $sign = \Tencentyun\Auth::getAppSignV2($bucket, "", $expired);
            if (!empty($sign)) {
                Yii::$app->cache->set($sign_cache, $sign, 500);
            }
        }
        if (empty($sign) || empty($url)) {
            $respMsg->return_msg = '获取签名失败';
            return $respMsg;
        } else {
            $url .= "/{$file}";
            $respMsg->return_code = RespMsg::SUCCESS;
            $respMsg->return_msg = ['url' => $url, 'sign' => $sign, "fileid" => $file];
            return $respMsg;
        }

    }
}