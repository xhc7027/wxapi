<?php

namespace app\commons;

use Tencentyun\ImageV2;
use Yii;

/**
 * 图片处理工具类
 * @package app\common
 */
class ImageUtil
{
    /**
     * 商城图片存储在腾讯万象优图的空间名称
     */
    const BUCKET = 'weixinapi';

    /**
     * 把图片上传到腾讯万象优图
     * @param $sourceFilePath 在本地可直接访问路径
     * @param $fileName 自定义文件名称
     * @return string 返回图片浏览地址
     */
    public static function uploadTencentYunImg($sourceFilePath, $fileName)
    {
        $fileId = $fileName . '_' . md5(microtime() . mt_rand());
        $uploadRet = ImageV2::upload($sourceFilePath, self::BUCKET, $fileId);

        if (0 === $uploadRet['code']) {
            return $uploadRet['data']['downloadUrl'];
        }

        Yii::warning('把图片上传到腾讯万象优图失败，具体原因：' . $uploadRet['message'], __METHOD__);

        return null;
    }

    /**
     * 万象优图裁剪缩略图添加参数
     * @param $path
     * @param $w
     * @param $h
     * @return string
     * ps:此处只简单提供一种裁剪方式，万象优图有大量的裁剪配置可以参考官方文档，另行配置
     * https://www.qcloud.com/doc/product/275/RESTful%20API#8.1-.E5.9F.BA.E6.9C.AC.E5.9B.BE.E5.83.8F.E5.A4.84.E7.90.86.EF.BC.88imageview2.EF.BC.89
     * 8.1 基本图像处理（imageView2）
     * 8.2 高级图像处理（imageMogr2）
     */
    public static function imgCropTX($path, $w, $h)
    {
        if (!empty($path)) {
            $param = "";
            if ($w > 0 && $h > 0) {
                $param = "imageMogr2/crop/!{$w}x{$h}";
            } else if ($w > 0 && empty($h)) {
                $param = "imageMogr2/crop/{$w}x";
            } else if ($h > 0 && empty($w)) {
                $param = "imageMogr2/crop/x{$h}";
            }
            if ($param != "") {
                //带上参数 进行缩放
                if (stripos($path, "?") === false) {
                    $path .= "?" . $param;
                } else {
                    $path .= "&" . $param;
                }
            }
        }
        return $path;
    }
}