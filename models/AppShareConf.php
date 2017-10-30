<?php

namespace app\models;

use Curl\Curl;
use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $id
 * @property string $appId
 * @property string $appSecret
 * @property integer $ticket
 * @property string $ticketTime
 * @property integer $accessToken
 * @property integer $tokenTime
 */
class AppShareConf extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => '编号',
            'appId' => '待获取账号的AppId',
            'appSecret' => '秘钥',
            'ticket' => '票据',
            'ticketTime' => '获取票据时间',
            'accessToken' => '令牌',
            'tokenTime' => '令牌有效时间',
        ];
    }

    /**
     * 获取待分享账号的数据
     * @return static
     */
    public function getShareInfo()
    {
        return AppShareConf::findOne(1);
    }

    /**
     * 获取代分享账号的票据
     * @return int|mixed
     */
    public function getJsApiTicket()
    {
        //1：票据:未过期则直接返回
        if ($this->ticketTime >= time() && $this->ticket) {
            return $this->ticket;
        }
        //2：票据过期，则通过token重新获取
        $dataArr = $this->getAccessToken();
        $accessToken = $dataArr['accessToken'];
        if ($accessToken) {
            return $this->doJsTicket($accessToken);
        }
        return false;
    }

    /**
     * 获取代分享账号的token
     * @return int|mixed
     */
    public function getAccessToken()
    {
        $dataArr = array();
        if ($this->tokenTime < time()) {
            $access_token = $this->doAccessToken();
        } else {
            $access_token = $this->accessToken;
        }
        $dataArr['accessToken'] = $access_token;
        $dataArr['expiresIn'] = $this->tokenTime - time();
        return $dataArr;
    }

    /**
     * 重新向微信获取代分享账号的token
     * 失败返回false，成功返回token
     * @return mixed
     */
    private function doAccessToken()
    {
        $access_token = false;
        $curl = new Curl();
        $curl->get(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/token',
            [
                'grant_type' => 'client_credential',
                'appid' => $this->appId,
                'secret' => $this->appSecret,
            ]
        );
        $result = json_decode($curl->response);
        if ($curl->error || (isset($result->errcode) && $result->errcode != 0)) {
            Yii::error('获取待分享账号的token失败，' . $curl->response, __METHOD__);
        } else {
            $access_token = $result->access_token;
            if ($access_token) {
                $this->tokenTime = time() + $result->expires_in - 200;
                $this->accessToken = $access_token;
                $this->save();
            }
        }
        $curl->close();

        return $access_token;
    }

    /**
     * 重新向微信获取代分享账号的票据
     * 失败返回false，成功返回token
     * @param $accessToken
     * @return mixed
     */
    private function doJsTicket($accessToken)
    {
        $ticket = false;
        $curl = new Curl();
        $curl->get(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/ticket/getticket',
            [
                'type' => 'jsapi',
                'access_token' => $accessToken
            ]
        );
        $result = json_decode($curl->response);
        if ($curl->error || (isset($result->errcode) && $result->errcode != 0)) {
            Yii::error('获取代分享账号的ticket失败，' . $curl->response, __METHOD__);
        } else {
            $ticket = $result->ticket;
            if ($result->ticket) {
                $this->ticketTime = time() + $result->expires_in - 200;
                $this->ticket = $ticket;
                $this->save();
            }
        }

        return $ticket;
    }
}
