<?php

namespace app\services;

use app\commons\HttpUtil;
use app\models\AppInfo;
use app\models\RespMsg;
use yii;

/**
 * 获取公众号数据统计接口数据
 *
 * @package app\services
 */
class DataService
{
    /**
     * @var string 公众号AppId
     */
    private $appId;
    /**
     * @var string 访问令牌
     */
    private $accessToken;
    /**
     * @var RespMsg 请求接口处理失败时才有值
     */
    private $respMsg = null;

    /**
     * DataService constructor.
     * @param AppInfo $appInfo
     */
    public function __construct($appInfo)
    {
        $this->appId = $appInfo->appId;
        //获取此公众号访问令牌
        $respMsg = Yii::$app->weiXinService->getAppAccessToken($appInfo);
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $this->accessToken = $respMsg->return_msg['accessToken'];
        } else {
            $this->respMsg = $respMsg;
        }
    }

    /**
     * 获取用户增减数据
     *
     * @param string $apiName "getusersummary"
     * @param string $beginDate "2014-12-02"
     * @param string $endDate "2014-12-07"
     * @return RespMsg
     */
    private function getData($apiName, $beginDate, $endDate)
    {
        if ($this->respMsg) {
            return $this->respMsg;
        }

        $url = Yii::$app->params['wxConfig']['appUrl'] . '/datacube/' . $apiName;
        $params = 'access_token=' . $this->accessToken;
        $body = json_encode([
            'begin_date' => $beginDate,
            'end_date' => $endDate,
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);

        return $respMsg;
    }

    /**
     * 整合多个接口
     * @param $beginDate
     * @param $endDate
     * @return RespMsg
     */
    public function getDataStatistics($beginDate, $endDate)
    {
        $respMsg = new RespMsg();

        $weiXinDataApi = Yii::$app->params['weiXinDataApi'];
        foreach ($weiXinDataApi as $item => $value) {
            $data = $this->getData($value['type'], $beginDate, $endDate);
            if ($data->return_code == RespMsg::SUCCESS) {
                $respMsg->return_msg[$value['type']] = ['list' => $data->return_msg->list];
            } else {
                $respMsg->return_msg[$value['type']] = ['error' => $value['name'] . json_encode($data->return_msg)];
            }
        }

        return $respMsg;
    }
}