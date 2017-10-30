<?php

namespace app\services;

use app\commons\FileUtil;
use app\commons\ImageUtil;
use Yii;
use yii\base\Exception;

class ImageService
{
    /**
     * 上传图片到腾讯优图，不压缩
     * @param $file  在本地可直接访问路径
     * @return string
     * @throws \yii\base\UserException
     */
    public static function uploadImage($file)
    {
        $path = ImageUtil::uploadTencentYunImg($file, 'wxapi');
        if ($path == null) {
            $path = $file;
        } else {
            try {
                FileUtil::delete($file, null, false);
            } catch (\Exception $e) {
                Yii::error($e->getMessage() . $file, __METHOD__);
            }
        }
        return $path;
    }

    /**
     * 从微信服务器获取图片，并存储在本地服务器
     * @param $path
     * @return null
     */
    public static function getWxImage($path)
    {
        if (strpos($path, "http") !== 0) {
            return false;
        }
        $dir = Yii::$app->basePath . '/web/upload';
        $filename = $dir . md5($path) . '.jpg';
        $package = self::downloadWeixinFile($path);
        $data = json_decode($package['body'], 1);
        if ($package['header']['http_code'] != 200 || !empty($data['errcode'])) {
            Yii::warning('download weixin img error, msg=' . json_encode($package['body']), __METHOD__);
            return false;
        }
        //获取成功
        if (!is_dir($dir)) {
            mkdir($dir, 0700);
        }
        try {
            $result = self::saveWeixinFile($filename, $package['body']);
        } catch (Exception $e) {
            echo $e->getMessage();
            Yii::warning('save img error, msg=' . $e->getMessage(), __METHOD__);
        }
        if ($result == false) {
            return false;
        }
        return $filename;
    }

    /**
     * 下载微信图片文件
     */
    public static function downloadWeixinFile($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);    //只取body头
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        $imageAll = array_merge(array('header' => $httpinfo), array('body' => $package));
        return $imageAll;
    }

    /**
     * 保存微信图片到本地
     */
    public static function saveWeixinFile($filename, $filecontent)
    {
        $local_file = fopen($filename, 'w');
        if (false !== $local_file) {
            if (false !== fwrite($local_file, $filecontent)) {
                fclose($local_file);
            }
        }
        if (file_exists($filename)) {
            return $filename;
        } else {
            return false;
        }
    }

}