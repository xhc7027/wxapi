<?php

namespace Idouzi\Commons;

use Idouzi\Commons\QCloud\Cos\Api;
use Idouzi\Commons\Exceptions\SystemException;
use Idouzi\Commons\QCloud\Cos\CosResponseData;
use Yii;

/**
 * 上传数据处理工具类
 *
 * @package Idouzi\Commons
 */
class UploadUtil
{
    /**
     * 把文件上传到腾讯云对象存储
     *
     * @param string $sourceFilePath 在本地可直接访问路径
     * @param string $fileName 自定义文件名称
     * @param string $type 文件原始类型
     * @return CosResponseData
     * @throws SystemException
     * @throws \Exception
     */
    public static function uploadCos($sourceFilePath, $fileName, string $type): CosResponseData
    {
        $fileId = date('YmdHis') . $fileName . mt_rand() . FileUtil::getSuffixNameByType($type);

        $cosApi = new Api(Yii::$app->params['qcloud']['cos']);

        $ret = $cosApi->upload(Yii::$app->params['qcloud']['cos']['bucket'], $sourceFilePath, $fileId);

        if (0 === $ret['code']) {
            $data = new CosResponseData();
            $data->accessUrl = $ret['data']['access_url'] ?? null;
            $data->resourcePath = $ret['data']['resource_path'] ?? null;
            $data->sourceUrl = $ret['data']['source_url'] ?? null;
            $data->url = $ret['data']['url'] ?? null;
            return $data;
        }

        Yii::error('把图片上传到腾讯失败，具体原因：' . json_encode($ret), __METHOD__);
        throw new SystemException('把图片上传到腾讯失败');
    }

    /**
     * 删除腾讯云对象存储上的文件
     *
     * @param array|string $paths
     * @return array;每个图片处理的结果{code:,message:''}
     * @throws \Exception
     */
    public static function deleteAll($paths)
    {
        if (empty($paths)) {
            return;
        }
        if (gettype($paths) == 'string') {
            $paths[] = $paths;
        }
        $cosApi = new Api(Yii::$app->params['qcloud']['cos']);
        $result = [];
        $bucket = Yii::$app->params['qcloud']['cos']['bucket'];
        foreach ($paths as $v) {
            //删除图片，返回的结果是{code:,message:''}
            $ret = $cosApi->delFile($bucket, $v);
            array_push($result, $ret);
        }
        return $result;
    }


}