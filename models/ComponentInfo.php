<?php

namespace app\models;

use app\commons\HttpUtil;
use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $appId
 * @property string $infoType
 * @property string $verifyTicket
 * @property integer $zeroUpdatedAt
 * @property string $accessToken
 * @property integer $zeroExpiresIn
 * @property integer $oneUpdatedAt
 */
class ComponentInfo extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'appId' => '第三方平台AppId',
            'infoType' => '信息类型',
            'verifyTicket' => '验证票据',
            'zeroUpdatedAt' => '票据更新时间',
            'accessToken' => '第三方平台访问令牌',
            'zeroExpiresIn' => '令牌有效期',
            'oneUpdatedAt' => '令牌更新时间',
        ];
    }

    /**
     * 获取第三方平台component_access_token<br>
     * 第三方平台compoment_access_token是第三方平台的下文中接口的调用凭据，也叫做令牌（component_access_token）。
     * 每个令牌是存在有效期（2小时）的，且令牌的调用不是无限制的，请第三方平台做好令牌的管理，在令牌快过期时（比如1小时50分）再进行刷新。
     * @return RespMsg {"return_code":"SUCCESS","return_msg":{"appId":"xxx","accessToken":"xxx","expiresIn":xxx}}
     */
    public function getAccessToken()
    {
        $respMsg = new RespMsg();

        //判断令牌是否存在并且有无过期
        if (isset($this->accessToken) && (time() - $this->oneUpdatedAt < 6600)) {
            $expiresIn = 6600 - (time() - $this->oneUpdatedAt);
            $respMsg->return_msg = [
                'appId' => $this->appId,
                'accessToken' => $this->accessToken,
                'expiresIn' => $expiresIn < 1 ? 1 : $expiresIn,
                'oneUpdatedAt' => $this->oneUpdatedAt,
            ];
            return $respMsg;
        }

        $url = Yii::$app->params['wxConfig']['url'] . '/api_component_token';
        $body = json_encode([
            'component_appid' => Yii::$app->params['wxConfig']['appId'],
            'component_appsecret' => Yii::$app->params['wxConfig']['secret'],
            'component_verify_ticket' => $this->verifyTicket
        ]);

        $respMsg = HttpUtil::post($url, null, $body);

        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $result = $respMsg->return_msg;
            //将令牌写入到数据库
            $this->accessToken = $result->component_access_token;
            $this->zeroExpiresIn = $result->expires_in;
            $this->oneUpdatedAt = time();
            if ($this->save()) {
                $respMsg->return_msg = null;
                $respMsg->return_msg = [
                    'appId' => $this->appId, 'accessToken' => $this->accessToken,
                    'expiresIn' => 6600, 'oneUpdatedAt' => $this->oneUpdatedAt,
                ];
            } else {
                Yii::error('保存数据到DB出错' . json_encode($this), __METHOD__);
            }
        }

        return $respMsg;
    }

    /**
     * 获取预授权码pre_auth_code<br>
     * 该API用于获取预授权码。预授权码用于公众号授权时的第三方平台方安全验证。
     * @return RespMsg {"return_code":"SUCCESS","return_msg":{"preAuthCode":"xxx"}}
     */
    public function getPreAuthCode()
    {
        $respMsg = $this->getAccessToken();
        if (!$respMsg->return_code === RespMsg::FAIL) {
            return $respMsg;
        }

        $url = Yii::$app->params['wxConfig']['url'] . '/api_create_preauthcode';
        $params = 'component_access_token=' . $respMsg->return_msg['accessToken'];
        $body = json_encode(['component_appid' => Yii::$app->params['wxConfig']['appId']]);

        $respMsg = HttpUtil::post($url, $params, $body);

        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $result = $respMsg->return_msg->pre_auth_code;
            $respMsg->return_msg = null;
            $respMsg->return_msg['preAuthCode'] = $result;
        }

        return $respMsg;
    }

    /**
     * 第三方平台对其所有API调用次数清零（只与第三方平台相关，与公众号无关，接口如api_component_token）\
     *
     * @param string $accessToken
     * @return RespMsg
     */
    public function clearQuota($accessToken)
    {
        $url = Yii::$app->params['wxConfig']['url'] . '/clear_quota';
        $params = 'component_access_token=' . $accessToken;
        $body = json_encode(['component_appid' => Yii::$app->params['wxConfig']['appId']]);

        $respMsg = HttpUtil::post($url, $params, $body);

        return $respMsg;
    }
}
