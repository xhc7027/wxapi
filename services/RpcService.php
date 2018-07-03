<?php

namespace app\services;

use app\commons\FileUtil;
use app\models\RespMsg;
use app\models\AppInfo;
use app\exceptions\SystemException;
use Yii;
use app\models\TsMsgSupplierFounder;

/**
 * <p>这是所有RPC服务调用的入口，在里面并不真正实现某些业务，而是由具体的算法实现来完成，
 * 里面的方法仅仅只是调用。</p>
 *
 * @package app\services
 */
class RpcService
{
    /**
     * 新增永久图文素材
     *
     * @param string $supplierId 公众号编号
     * @param array $data 要上传的图文数据
     * @return array ['mediaId' => 'xxx']
     */
    public function materialAddNews(string $supplierId, array $data)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg->return_msg['mediaId'] = Yii::$app->weiXinService->materialAddNews($supplierId, $data);
        } catch (\Exception $e) {
            Yii::error('新增永久图文素材业务异常:' . $e->getMessage(), __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 根据标签进行群发【订阅号与服务号认证后均可用】
     *
     * @param string $supplierId 商家编号
     * @param array $data
     * @return array
     */
    public function messageMassSendAll(string $supplierId, array $data)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg->return_msg = Yii::$app->weiXinService->messageMassSendAll($supplierId, $data);
        } catch (\Exception $e) {
            Yii::error('根据标签进行群发异常：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', data:' . json_encode($data), __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 预览接口【订阅号与服务号认证后均可用】
     *
     * @param string $supplierId 商家编号
     * @param string $toWXName 接收消息用户对应该公众号的openid，该字段也可以改为towxname，以实现对微信号的预览
     * @param string $mediaId 用于群发的消息的media_id
     * @return array
     */
    public function messageMassPreview(string $supplierId, string $toWXName, string $mediaId)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg = Yii::$app->weiXinService->messageMassPreview($supplierId, $toWXName, $mediaId);
        } catch (\Exception $e) {
            Yii::error('预览接口：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', toWXName:' . $toWXName . ', mediaId:' . $mediaId, __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 上传图文消息内的图片获取URL
     *
     * @param string $supplierId 商家编号
     * @param string $fileType 媒体文件类型，这里填写上传文件的源类型，例如“image/png”
     * @param string $url 远程文件访问地址
     * @return array ['url' => 'xxx']
     */
    public function mediaUploadImg(string $supplierId, string $fileType, string $url)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg->return_msg = Yii::$app->weiXinService->mediaUploadImg(
                $supplierId,
                FileUtil::getSuffixNameByType($fileType),
                $url
            );
        } catch (\Exception $e) {
            Yii::error('上传图文消息内的图片获取URL接口：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', url:' . $url, __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 新增其他类型永久素材<br>
     *
     * 通过POST表单来调用接口，表单id为media，包含需要上传的素材内容，有filename、filelength、content-type等信息。
     * 请注意：图片素材将进入公众平台官网素材管理模块中的默认分组。
     *
     * @param string $supplierId 商家编号
     * @param string $fileType 媒体文件类型，这里填写上传文件的源类型，例如“image/png”
     * @param string $url 远程文件访问地址
     * @return array
     */
    public function materialAddMaterial(string $supplierId, string $fileType, string $url)
    {
        $resMsg = new RespMsg();
        try {
            $ret = null;
            //选择媒体文件类型
            if (in_array($fileType, ['image/bmp', 'image/png', 'image/jpeg', 'image/jpg', 'image/gif'])) {
                //图片（image）
                $ret = Yii::$app->weiXinService->materialAddMaterialForImage(
                    $supplierId,
                    FileUtil::getSuffixNameByType($fileType),
                    $url
                );
            } else if (in_array($fileType, [''])) {
                //语音（voice）
            } else if (in_array($fileType, [''])) {
                //视频（video）
            } else if (in_array($fileType, [''])) {
                //缩略图（thumb）
            }
            $resMsg->return_msg = $ret;
        } catch (\Exception $e) {
            Yii::error('新增其他类型永久素材接口：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', fileType:' . $fileType . ', url:' . $url, __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 删除永久素材<br>
     *
     * 在新增了永久素材后，开发者可以根据本接口来删除不再需要的永久素材，节省空间。
     * 请注意：
     * 1、请谨慎操作本接口，因为它可以删除公众号在公众平台官网素材管理模块中新建的图文消息、语音、视频等素材
     * 2、临时素材无法通过本接口删除
     * 3、调用该接口需https协议
     *
     * @param string $supplierId 商家编号
     * @param string $mediaId 要获取的素材的media_id
     * @return array
     */
    public function materialDelMaterial(string $supplierId, string $mediaId)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg->return_msg = Yii::$app->weiXinService->materialDelMaterial($supplierId, $mediaId);
        } catch (\Exception $e) {
            Yii::error('删除永久素材：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', mediaId:' . $mediaId, __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 修改永久图文素材<br>
     *
     * 开发者可以通过本接口对永久图文素材进行修改。
     * 请注意：
     * 1、也可以在公众平台官网素材管理模块中保存的图文消息（永久图文素材）
     * 2、调用该接口需https协议
     *
     * @param string $supplierId 商家编号
     * @param int $index 文章位置索引
     * @param array $data 要修改的文章内容
     * @return array
     */
    public function materialUpdateNews(string $supplierId, int $index, array $data)
    {
        $resMsg = new RespMsg();
        try {
            $resMsg->return_msg = Yii::$app->weiXinService->materialUpdateNews($supplierId, $index, $data);
        } catch (\Exception $e) {
            Yii::error('修改永久图文素材：' . $e->getMessage() . ', supplierId:' . $supplierId
                . ', index:' . $index, __METHOD__);
            $resMsg->return_code = RespMsg::FAIL;
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg->toArray();
    }

    /**
     * 消息推送
     * @param array $pushData
     * @param string $templateId
     * @return RespMsg
     */
    public function msgPush(array $pushData, string $templateId)
    {
        $resMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        try {
            $resMsg->return_msg = Yii::$app->weiXinService->pushMsg($pushData, $templateId);
            $resMsg->return_code = RespMsg::SUCCESS;
        } catch (\Exception $e) {
            Yii::warning('推送失败:' . $e->getMessage(), __METHOD__);
            $resMsg->return_msg = $e->getMessage();
        }

        return $resMsg;
    }

    /**
     * 正常情况下将返回下面数据：<br>
     * <code>
     * {
     *   "return_code":"SUCCESS",
     *   "return_msg":
     *     {
     *       "appId":"wx57b1371bda498a46",
     *       "nonceStr":"hgJTNdOA1JjZTcFi",
     *       "timestamp":1476264449,
     *       "url":"http:\/\/weixinapi.idouzi.com\/facade\/web-page?appid=wx57b1371bda498a46",
     *       "signature":"cbd0ba3ae922916af55aa32b5c010661a452835c",
     *     }
     * }
     * </code>
     * @param string $appid 公众号appId
     * @param string $url 用户访问的当前网页地址
     * @return string
     */
    public function getJsSdkConfFromApi($id, $url, $type)
    {
        $respMsg = new RespMsg();
        //1.如果请求的方式不是appId或wxId中的一个
        if (!in_array($type, array('appId', 'wxId'))) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '请求类型有误';
            return $respMsg;
        }

        $signPackage = Yii::$app->weiXinService->getSignPackage4Js($url, $id, $type);

        $respMsg->return_msg = $signPackage;
        return $respMsg;
    }

    /**
     * 检验事务id是否存在
     *
     * @param $tsId string 事务id
     * @return array {
     * {
     * "return_code": "SUCCESS",
     * "return_msg": true/false,//true表示存在，false不存在
     * }
     */
    public function checkTsId(string $tsId)
    {
        $respMsg = new RespMsg();
        try {
            $respMsg->return_msg = TsMsgSupplierFounder::findOne($tsId) ? true : false;
        } catch (Exception $e) {
            Yii::warning('检验事务id是否存在' . $e->getMessage(), __METHOD__);
            $respMsg->return_msg = $e->getMessage();
            $respMsg->return_code = RespMsg::FAIL;
        }
        return $respMsg->toArray();
    }

    /**
     * 获取商家的公众号信息
     *
     * @param int $wxid 微信id(商家id)
     * @return RespMsg
     */
    public function getAppInfo(int $wxid)
    {
        $model = AppInfo::find()->select(['headImg', 'nickName', 'verifyTypeInfo', 'qrcodeUrl', 'wxId as id', 'appId', 'serviceTypeInfo'])
            ->where(['wxId' => $wxid])->asArray()->one();
        if (!$model) {
            Yii::error('获取商家公众号信息错误, 错误的wxid是' . $wxid);
            return new RespMsg(['return_code' => RespMsg::FAIL]);
        }
        return new RespMsg(['return_msg' => $model]);
    }

    /**
     * 通过openId获取用户的信息
     *
     * @param string $appId 公众号id
     * @param string $openId
     * @return RespMsg
     */
    public function getUserInfoByOpenId(string $appId, string $openId)
    {
        $respMsg = new RespMsg();
        try {
            $respMsg->return_msg = Yii::$app->weiXinService->getUserInfoByOpenId($appId, $openId);
        } catch (SystemException $e) {
            $respMsg->return_msg = $e->getMessage();
            $respMsg->return_code = RespMsg::FAIL;
        } catch (\Exception $e) {
            Yii::error('通过openId获取用户信息失败, 失败的appId是' . $appId . ', openId是' . $openId, __METHOD__);
            $respMsg->return_msg = $e->getMessage();
            $respMsg->return_code = RespMsg::FAIL;
        }
        return $respMsg->toArray();
    }
}