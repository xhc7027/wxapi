<?php

namespace app\models;

use app\commons\HttpUtil;
use app\commons\SecurityUtil;
use app\services\ImageService;
use app\services\WeiXinService;
use Curl\Curl;
use Yii;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;

/**
 * @property string $appId
 * @property string $accessToken
 * @property string $refreshToken
 * @property string $funcScopeCategory
 * @property integer $expiresIn
 * @property integer $zeroUpdatedAt
 * @property string $nickName
 * @property string $headImg
 * @property integer $serviceTypeInfo
 * @property integer $verifyTypeInfo
 * @property string $userName
 * @property string $alias
 * @property integer $businessInfoOpenStore
 * @property integer $businessInfoOpenScan
 * @property integer $businessInfoOpenPay
 * @property integer $businessInfoOpenCard
 * @property integer $businessInfoOpenShake
 * @property string $qrcodeUrl
 * @property integer $oneUpdatedAt
 * @property string $componentAppId
 * @property string $infoType
 * @property string $authorizationCode
 * @property integer $authorizationCodeExpiredTime
 * @property integer $twoUpdatedAt
 * @property integer $wxId
 */
class AppInfo extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'appId' => '授权方AppId',
            'accessToken' => '[0]授权方接口调用凭据',
            'refreshToken' => '[0]接口调用凭据刷新令牌',
            'funcScopeCategory' => '[0]公众号授权给开发者的权限集列表',
            'expiresIn' => '[0]有效期',
            'zeroUpdatedAt' => '[0]更新时间戳',
            'nickName' => '[1]授权方昵称',
            'headImg' => '[1]授权方头像',
            'serviceTypeInfo' => '[1]授权方公众号类型',
            'verifyTypeInfo' => '[1]授权方认证类型',
            'userName' => '[1]授权方公众号的原始ID',
            'alias' => '[1]授权方公众号所设置的微信号',
            'businessInfoOpenStore' => '[1]是否开通微信门店功能',
            'businessInfoOpenScan' => '[1]是否开通微信扫商品功能',
            'businessInfoOpenPay' => '[1]是否开通微信支付功能',
            'businessInfoOpenCard' => '[1]是否开通微信卡券功能',
            'businessInfoOpenShake' => '[1]是否开通微信摇一摇功能',
            'qrcodeUrl' => '[1]二维码图片的URL',
            'oneUpdatedAt' => '[1]更新时间戳',
            'componentAppId' => '[2]第三方平台AppId',
            'infoType' => '[2]授权状态',
            'authorizationCode' => '[2]授权码',
            'authorizationCodeExpiredTime' => '[2]授权码过期时间',
            'twoUpdatedAt' => '[2]更新时间戳',
            'wxid' => '商家wxId'
        ];
    }

    /**
     * 使用授权码换取公众号的接口调用凭据和授权信息<br>
     * <p>
     * 该API用于使用授权码换取授权公众号的授权信息，并换取authorizer_access_token和authorizer_refresh_token。
     * 授权码的获取，需要在用户在第三方平台授权页中完成授权流程后，在回调URI中通过URL参数提供给第三方平台方。
     * </p><p>
     * 请注意，由于现在公众号可以自定义选择部分权限授权给第三方平台，因此第三方平台开发者需要通过该接口来获取公众号具体授权了哪些权限，
     * 而不是简单地认为自己声明的权限就是公众号授权的权限。
     * </p>
     * @param string $componentAccessToken 公众号第三方平台访问令牌
     * @return RespMsg
     * {
     *     "return_code":"SUCCESS",
     *     "return_msg":
     *     {
     *         "accessToken":"xxx","appId":"xxx","expiresIn":xxx,"zeroUpdatedAt":"xxx",
     *         "authorizationCode":"xxx","authorizationCodeExpiredTime":"xxx"
     *     }
     * }
     */
    public function getAuth($componentAccessToken)
    {
        $respMsg = new RespMsg();

        //判断凭据和授权信息是否存在并且有无过期
        if ($this->accessToken) {//如果数据库中已有记录
            if (time() - $this->zeroUpdatedAt < 6600) {//并且没有过期则直接使用DB中的数据
                $expiresIn = 6600 - (time() - $this->zeroUpdatedAt);
                $respMsg->return_msg = [
                    'accessToken' => $this->accessToken,
                    'appId' => $this->appId,
                    'expiresIn' => $expiresIn < 0 ? 1 : $expiresIn,
                    'zeroUpdatedAt' => $this->zeroUpdatedAt,
                    'authorizationCode' => $this->authorizationCode,
                    'authorizationCodeExpiredTime' => $this->authorizationCodeExpiredTime,
                ];
                return $respMsg;
            }
        }

        //如果访问令牌不存在但是刷新令牌存在，则调用接口进行重新刷取
        if ($this->refreshToken) {
            $respMsg = $this->getAuthByRefresh($componentAccessToken);
            return $respMsg;
        }

        $respMsg->return_code = RespMsg::FAIL;
        $respMsg->return_msg = '公众号没有经过授权';
        return $respMsg;
    }

    /**
     * <p>使用授权码换取公众号的接口调用凭据和授权信息</p>
     *
     * 这种方法只适合于公众号授权绑定时才可以调用，后续想要获取公众号信息请使用getAuthorizeInfo()
     *
     * @param string $componentAccessToken
     * @return RespMsg {"return_code":"SUCCESS","return_msg":{"authorization_info":{...}}}
     */
    public function getQueryAuth($componentAccessToken)
    {
        $url = Yii::$app->params['wxConfig']['url'] . '/api_query_auth';
        $params = 'component_access_token=' . $componentAccessToken;
        $body = json_encode([
            'component_appid' => $this->componentAppId ? strval($this->componentAppId) :
                Yii::$app->params['wxConfig']['appId'],
            'authorization_code' => strval($this->authorizationCode),
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);

        return $respMsg;
    }

    /**
     * 获取（刷新）授权公众号的接口调用凭据（令牌）
     * @param string $componentAccessToken 第三方平台访问令牌
     * @return RespMsg
     * {
     *     "return_code":"SUCCESS",
     *     "return_msg":
     *     {
     *         "accessToken":"xxx","appId":"xxx","expiresIn":xxx,"zeroUpdatedAt":"xxx",
     *         "authorizationCode":"xxx","authorizationCodeExpiredTime":"xxx"
     *     }
     * }
     */
    private function getAuthByRefresh($componentAccessToken)
    {
        $url = Yii::$app->params['wxConfig']['url'] . '/api_authorizer_token';
        $params = 'component_access_token=' . $componentAccessToken;
        $body = json_encode([
            'component_appid' => $this->componentAppId ? strval($this->componentAppId) :
                Yii::$app->params['wxConfig']['appId'],
            'authorizer_appid' => strval($this->appId),
            'authorizer_refresh_token' => strval($this->refreshToken),
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);

        $result = $respMsg->return_msg;
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            //将凭据和授权信息写入到数据库
            $this->accessToken = $result->authorizer_access_token;
            $this->refreshToken = $result->authorizer_refresh_token;
            if (isset($result->expires_in)) {//在授权的公众号具备API权限时，才有此返回值
                $this->expiresIn = $result->expires_in;
            }
            $this->zeroUpdatedAt = time();
            if ($this->save()) {
                $respMsg->return_msg = null;
                $respMsg->return_msg = [
                    'accessToken' => $this->accessToken,
                    'appId' => $this->appId,
                    'expiresIn' => 6600,
                    'zeroUpdatedAt' => $this->zeroUpdatedAt,
                    'authorizationCode' => $this->authorizationCode,
                    'authorizationCodeExpiredTime' => $this->authorizationCodeExpiredTime,
                ];
            } else {
                Yii::warning('保存数据到DB出错' . json_encode($this), __METHOD__);
            }
        }

        return $respMsg;
    }

    /**
     * 获取授权方的公众号帐号基本信息
     * @param string $componentAccessToken 第三方平台访问令牌
     * @return RespMsg
     * {
     *     "return_code":"SUCCESS",
     *     "return_msg":
     *     {
     *         "accessToken":"xxx","appId":"xxx","expiresIn":xxx,"zeroUpdatedAt":"xxx",
     *         "authorizationCode":"xxx","authorizationCodeExpiredTime":"xxx"
     *     }
     * }
     */
    public function getAuthorizeInfo($componentAccessToken)
    {
        $url = Yii::$app->params['wxConfig']['url'] . '/api_get_authorizer_info';
        $params = 'component_access_token=' . $componentAccessToken;
        $body = json_encode([
            'component_appid' => $this->componentAppId ? strval($this->componentAppId) :
                Yii::$app->params['wxConfig']['appId'],
            'authorizer_appid' => strval($this->appId),
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);

        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $result = $respMsg->return_msg;
            $authorizeInfo = $result->authorizer_info;
            $this->nickName = isset($authorizeInfo->nick_name) ? $authorizeInfo->nick_name : null;
            $this->headImg = isset($authorizeInfo->head_img) ? $authorizeInfo->head_img : null;
            $this->serviceTypeInfo = $authorizeInfo->service_type_info->id;
            $this->verifyTypeInfo = $authorizeInfo->verify_type_info->id;
            $this->userName = isset($authorizeInfo->user_name) ? $authorizeInfo->user_name : null;
            $this->alias = isset($authorizeInfo->alias) ? $authorizeInfo->alias : null;
            $this->qrcodeUrl = $this->uploadImage($authorizeInfo->qrcode_url);
            $this->businessInfoOpenStore = $authorizeInfo->business_info->open_store;
            $this->businessInfoOpenScan = $authorizeInfo->business_info->open_scan;
            $this->businessInfoOpenPay = $authorizeInfo->business_info->open_pay;
            $this->businessInfoOpenCard = $authorizeInfo->business_info->open_card;
            $this->businessInfoOpenShake = $authorizeInfo->business_info->open_shake;
            $this->oneUpdatedAt = time();
            $funcInfoAry = $result->authorization_info->func_info;
            foreach ($funcInfoAry as $category) {
                $tmpAry[] = $category->funcscope_category->id;
            }
            $this->funcScopeCategory = json_encode($tmpAry);
            if (!$this->save()) {
                Yii::warning('保存数据到DB出错' . json_encode($this), __METHOD__);
            }

            $respMsg->return_msg = null;
            $respMsg->return_msg = [
                'accessToken' => $this->accessToken, 'appId' => $this->appId,
                'expiresIn' => 6600, 'zeroUpdatedAt' => $this->zeroUpdatedAt,
                'authorizationCode' => $this->authorizationCode,
                'authorizationCodeExpiredTime' => $this->authorizationCodeExpiredTime,
            ];
        }

        return $respMsg;
    }

    /**
     * 获取授权方的选项设置信息
     * @param string $optionName 选项名称
     * @return RespMsg
     */
    public function getAuthorizeOption($optionName)
    {
        //TODO 实现有问题，后续有用到再做
        $url = Yii::$app->params['wxConfig']['url'] . '/api_get_authorizer_option';
        $params = 'component_access_token=' . $this->accessToken;
        $body = json_encode([
            'component_appid' => $this->componentAppId ? strval($this->componentAppId) :
                Yii::$app->params['wxConfig']['appId'],
            'authorizer_appid' => strval($this->appId),
            'option_name' => $optionName
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);
        return $respMsg;
    }

    /**
     * 设置授权方的选项信息
     * @param string $optionName 选项名称
     * @param string $optionValue 选项值
     * @return RespMsg
     */
    public function setAuthorizeOption($optionName, $optionValue)
    {
        //TODO 实现有问题，后续有用到再做
        $url = Yii::$app->params['wxConfig']['url'] . '/api_set_authorizer_option';
        $params = 'component_access_token=' . strval($this->accessToken);
        $body = json_encode([
            'component_appid' => $this->componentAppId ? strval($this->componentAppId) :
                Yii::$app->params['wxConfig']['appId'],
            'authorizer_appid' => strval($this->appId),
            'option_name' => $optionName,
            'option_value' => $optionValue
        ]);

        $respMsg = HttpUtil::post($url, $params, $body);
        return $respMsg;
    }

    /**
     * 公众号调用或第三方代公众号调用对公众号的所有API调用（包括第三方代公众号调用）次数进行清零<br>
     *
     * 正常情况下，会返回：
     * {
     *   "errcode":0,
     *   "errmsg":"ok"
     * }
     * @param string $componentAccessToken
     * @return RespMsg
     */
    public function clearQuota($componentAccessToken)
    {
        $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/clear_quota';
        $respMsg = $this->getAuth($componentAccessToken);
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $params = 'access_token=' . strval($respMsg->return_msg['accessToken']);
            $body = json_encode(['appid' => $this->appId]);
            $respMsg = HttpUtil::post($url, $params, $body);
        }

        return $respMsg;
    }

    /**
     * 下载图片，并存储到万象优图
     * @param $path
     * @return null|string
     */
    public function uploadImage($path)
    {
        $tx_path = null;
        $file_name = ImageService::getWxImage($path);
        if ($file_name != false) {
            $tx_path = ImageService::uploadImage($file_name);
        }
        return $tx_path ? $tx_path : $path;
    }
}
