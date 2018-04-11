<?php

namespace app\services;

use app\commons\FileUtil;
use app\commons\HttpUtil;
use app\commons\SecurityUtil;
use app\commons\StringUtil;
use app\exceptions\SystemException;
use app\models\AppInfo;
use app\models\ComponentInfo;
use app\models\RespMsg;
use app\models\WebUserAuthInfo;
use Curl\Curl;
use Yii;
use yii\base\ErrorException;
use yii\base\InvalidParamException;
use yii\web\Cookie;

/**
 * 公众号第三方平台业务处理
 * @package app\services
 */
class WeiXinService
{
    /**
     * @var 取消授权通知
     */
    const UNAUTHORIZED = 'unauthorized';
    /**
     * @var 授权成功通知
     */
    const AUTHORIZED = 'authorized';
    /**
     * @var 授权更新通知
     */
    const UPDATEAUTHORIZED = 'updateauthorized';


    /**
     * 用来处理每十分钟推送过来的验证令牌<br>
     *
     * 目前的处理方式：直接保存到数据库中
     * @param $decodeXMLObj 解密后的XML对象
     */
    public function saveVerifyTicket($decodeXMLObj)
    {
        $componentInfo = ComponentInfo::findOne(strval($decodeXMLObj->AppId[0]));
        if (!$componentInfo) {
            $componentInfo = new ComponentInfo();
            $componentInfo->appId = $decodeXMLObj->AppId[0];
        }
        $componentInfo->infoType = $decodeXMLObj->InfoType[0];
        $componentInfo->verifyTicket = $decodeXMLObj->ComponentVerifyTicket[0];
        $componentInfo->zeroUpdatedAt = $decodeXMLObj->CreateTime[0];
        if (!$componentInfo->save()) {
            Yii::warning('保存验证令牌到DB失败：' . $decodeXMLObj->asXML(), __METHOD__);
        }
    }

    /**
     * 用来处理公众号授权变动业务
     * @param \SimpleXMLElement $decodeXMLObj
     */
    public function handleChangeAuthorization($decodeXMLObj)
    {
        //先保存推送过来的变更信息
        $appInfo = AppInfo::findOne(strval($decodeXMLObj->AuthorizerAppid[0]));
        if (!$appInfo) {
            $appInfo = new AppInfo();
            $appInfo->appId = $decodeXMLObj->AuthorizerAppid[0];
        }
        $appInfo->componentAppId = $decodeXMLObj->AppId[0];
        $appInfo->infoType = $decodeXMLObj->InfoType[0];
        $appInfo->authorizationCode = $decodeXMLObj->AuthorizationCode[0];

        if (self::UNAUTHORIZED != $appInfo->infoType) {
            $appInfo->authorizationCodeExpiredTime = $decodeXMLObj->AuthorizationCodeExpiredTime[0];
            $appInfo->twoUpdatedAt = $decodeXMLObj->CreateTime[0];
        } else {
            $appInfo->authorizationCode = null;
            $appInfo->twoUpdatedAt = time();
            $appInfo->wxId = null;
        }

        if (!$appInfo->save()) {
            Yii::warning('保存失败：' . $decodeXMLObj->asXML(), __METHOD__);
        }

        if (self::UNAUTHORIZED != $appInfo->infoType) {
            $respMsg = $this->getComponentAccessToken();
            if ($respMsg->return_code === RespMsg::SUCCESS) {
                $appInfo->getAuth($respMsg->return_msg['accessToken']);
                $appInfo->getAuthorizeInfo($respMsg->return_msg['accessToken']);
            } else {
                Yii::error('更新公众号信息出错，没有获取到第三方平台访问令牌', __METHOD__);
            }
        } else {
            //清除此公众号除以外的信息，避免后续授权时使用之前的旧信息
            $key = 'app_access_token_' . $appInfo->appId;
            Yii::$app->cache->delete($key);
            $appInfo->delete();
            $tmpAppInfo = new AppInfo();
            $tmpAppInfo->appId = $appInfo->appId;
            $tmpAppInfo->nickName = $appInfo->nickName;
            $tmpAppInfo->serviceTypeInfo = $appInfo->serviceTypeInfo;
            $tmpAppInfo->userName = $appInfo->userName;
            $tmpAppInfo->infoType = self::UNAUTHORIZED;
            $tmpAppInfo->insert();

            //主动通知爱豆子此用户已经取消绑定
            $curl = new Curl();
            $params = [
                'appId' => $appInfo->appId,
                'apikey' => 839,
                'r' => 'supplier/api/unbundling',
                'timestamp' => time(),
            ];
            try {
                $sign = (new SecurityUtil($params, Yii::$app->params['signKey']['iDouZiSignKey']))->generateSign();
                $params['sign'] = $sign;
                $curl->get(Yii::$app->params['serviceDomain']['iDouZiDomain'] . '/index.php', $params);
                if ($curl->error) {
                    Yii::error('主动通知爱豆子用户:' . $appInfo->appId . '取消绑定时发现错误', __METHOD__);
                }
            } catch (InvalidParamException $e) {
                Yii::error('在对参数:' . json_encode($params) . ',签名时发现错误:' . $e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * 提供一个统一获取第三方公众平台账号信息的入口<br>
     * 此处会对相关信息进行缓存，当缓存失效时走读取DB流程。
     * @return RespMsg {"return_code":"SUCCESS","return_msg":{"appId","xxx","accessToken":"xxx","expiresIn":"xxx"}}
     */
    public function getComponentAccessToken()
    {
        $respMsg = new RespMsg();

        //判断缓存中是否存在令牌
        $key = 'component_access_token_' . Yii::$app->params['wxConfig']['appId'];
        try {
            $accessTokenAry = json_decode(Yii::$app->cache->get($key), true);
            if ($accessTokenAry) {
                $oneUpdatedAt = $accessTokenAry['oneUpdatedAt'];
                $expiresIn = 6600 - (time() - $oneUpdatedAt);
                $accessTokenAry['expiresIn'] = $expiresIn;
                $respMsg->return_msg = $accessTokenAry;
                Yii::$app->cache->set($key, json_encode($respMsg->return_msg), $respMsg->return_msg['expiresIn']);
                return $respMsg;
            }
        } catch (ErrorException $e) {
            Yii::$app->cache->delete($key);
        }

        //如果不存在则说明已经过期，此时重新获取并加入到缓存
        $componentInfo = ComponentInfo::findOne(Yii::$app->params['wxConfig']['appId']);
        $respMsg = $componentInfo->getAccessToken();

        if ($respMsg->return_code === RespMsg::SUCCESS) {
            Yii::$app->cache->set($key, json_encode($respMsg->return_msg), $respMsg->return_msg['expiresIn']);
        }

        return $respMsg;
    }

    /**
     * 获取预授权码pre_auth_code
     * @return RespMsg {"return_code":"SUCCESS","return_msg":{"preAuthCode":"xxx"}}
     */
    public function getComponentPreAuthCode()
    {
        $respMsg = new RespMsg();
        $appId = Yii::$app->params['wxConfig']['appId'];
        $componentInfo = ComponentInfo::findOne($appId);
        if ($componentInfo) {
            $respMsg = $componentInfo->getPreAuthCode();
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到第三方平台账号:' . $appId;
        }
        return $respMsg;
    }

    /**
     * 获取公众号访问令牌
     * @param AppInfo $appInfo 公众号模型
     * @return RespMsg
     * {
     *     "return_code":"SUCCESS",
     *     "return_msg":{
     *         "accessToken":"xxx","expiresIn":"xxx","authorizationCode":"xxx","authorizationCodeExpiredTime":"xxx"
     *     }
     * }
     */
    public function getAppAccessToken($appInfo)
    {
        $respMsg = new RespMsg();

        //判断缓存中是否存在令牌
        $key = 'app_access_token_' . $appInfo->appId;
        try {
            $accessTokenAry = json_decode(Yii::$app->cache->get($key), true);
            if ($accessTokenAry) {
                $expiresIn = 6600 - (time() - $accessTokenAry['zeroUpdatedAt']);
                $accessTokenAry['expiresIn'] = $expiresIn;
                $respMsg->return_msg = $accessTokenAry;
                Yii::$app->cache->set($key, json_encode($respMsg->return_msg), $respMsg->return_msg['expiresIn']);
                return $respMsg;
            }
        } catch (ErrorException $e) {
            Yii::$app->cache->delete($key);
        }

        //如果不存在则说明已经过期，此时重新获取并加入到缓存
        $respMsg = $this->getComponentAccessToken();
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $respMsg = $appInfo->getAuth($respMsg->return_msg['accessToken']);
            if ($respMsg->return_code === RespMsg::SUCCESS) {
                Yii::$app->cache->set($key, json_encode($respMsg->return_msg), $respMsg->return_msg['expiresIn']);
            }
        }

        return $respMsg;
    }

    /**
     * 发起网页授权：获取access_token, 通过code换取access_token
     * @param string $appId 公众号appId
     * @param string $code 在页面上获取的code
     * @return RespMsg
     */
    public function getWebPageAccessToken($appId, $code)
    {
        $componentAppId = Yii::$app->params['wxConfig']['appId'];
        $respMsg = $this->getComponentAccessToken();
        if ($respMsg->return_code == RespMsg::FAIL) {
            Yii::warning('根据公众号：' . $appId . '和code：' . $code . '获取网页访问令牌时没有得到第三方平台令牌', __METHOD__);
            return $respMsg;
        }

        $componentAccessToken = $respMsg->return_msg['accessToken'];
        $url = Yii::$app->params['wxConfig']['snsOauth2Url'] . '/access_token';
        $params = [
            'appid' => $appId,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'component_appid' => $componentAppId,
            'component_access_token' => $componentAccessToken,
        ];

        $respMsg = HttpUtil::get($url, http_build_query($params));
        if ($respMsg->return_code == RespMsg::FAIL) {
            if ($respMsg->return_msg->errcode == 40001) {
                //清空缓存和数据库，再一次调用本方法。
                $key = 'component_access_token_' . Yii::$app->params['wxConfig']['appId'];
                Yii::$app->cache->delete($key);
                $componentInfo = ComponentInfo::findOne(Yii::$app->params['wxConfig']['appId']);
                $componentInfo->accessToken = null;
                $componentInfo->update();
                Yii::warning('第一次获取第三方公众号平台访问令牌出错 ' . json_encode($respMsg) . ' 接下来将重新获取', __METHOD__);
                $this->getWebPageAccessToken($appId, $code);
            }
        }

        return $respMsg;
    }

    /**
     * 发起网页授权：刷新access_token
     * @param string $appId 公众号appId
     * @param string $refreshToken
     * @return RespMsg
     */
    public function getWebPageRefreshToken($appId, $refreshToken)
    {
        $componentInfo = ComponentInfo::findOne(Yii::$app->params['wxConfig']['appId']);
        $respMsg = $componentInfo->getAccessToken();
        $componentAccessToken = $respMsg->return_msg['accessToken'];

        $url = Yii::$app->params['wxConfig']['snsOauth2Url'] . '/refresh_token';
        $params = [
            'appid' => $appId,
            'grant_type' => 'refresh_token',
            'component_appid' => $componentInfo->appId,
            'component_access_token' => $componentAccessToken,
            'refresh_token' => $refreshToken,
        ];

        return HttpUtil::get($url, http_build_query($params));
    }

    /**
     * 获取网页JS SDK通过config接口注入权限验证配置信息<br>
     *
     * 签名生成规则如下（以下从微信文档处摘录）：
     * 参与签名的字段包括noncestr（随机字符串）,
     * 有效的jsapi_ticket, timestamp（时间戳）,
     * url（当前网页的URL，不包含#及其后面部分） 。
     * 对所有待签名参数按照字段名的ASCII 码从小到大排序（字典序）后，使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串string1。
     * 这里需要注意的是所有参数名均为小写字符。对string1作sha1加密，字段名和字段值都采用原始值，不进行URL 转义。
     * @param string $appId 公众号appId(不传，则用代分享账号)
     * @param string $url 用户当前访问页面
     * @param string $type 获取的方式,可以使appId或者wxId
     * @return array
     */
    public function getSignPackage4Js($url, $appId = null, $type = 'appId')
    {
        // 选择实际调用的模型
        $jsApiTicket = null;
        $appModel = new AppChooseServices($appId, 'jsSdkShare', $type);
        $appId = $appModel->getAppId();
        if (!$appModel->getSupplierIdApp()) {//判断是代分享还是自身的公众号
            $jsApiTicket = $appModel->getJsApiTicket();
        } else {
            $jsApiTicket = $this->getJsApiTicket($appId);
        }
        if (!$jsApiTicket) {
            return new RespMsg(['return_code' => RespMsg::FAIL, 'return_msg' => '获取票据失败']);
        }
        $timestamp = time();
        $nonceStr = StringUtil::getRandomStr();

        //按ASCII码升序排序
        $string = "jsapi_ticket=$jsApiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);

        $signPackage = [
            "appId" => $appId,
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
        ];
        return $signPackage;
    }

    /**
     * jsapi_ticket是公众号用于调用微信JS接口的临时票据<br>
     * @param string $appId 公众号appId
     * @return strin|null
     */
    private function getJsApiTicket($appId)
    {
        //判断缓存中是否有票据，默认仅缓存7200秒（为安全起见实际生命周期6600秒）
        $ticket = json_decode(Yii::$app->cache->get('wx_' . $appId . '_ticket'));
        if (!$ticket) {//缓存不存在则表示已经过期
            $appInfo = AppInfo::findOne($appId);
            $resp = $this->getAppAccessToken($appInfo);
            if ($resp->return_code == RespMsg::FAIL) {
                return null;
            } else {
                $accessToken = $resp->return_msg['accessToken'] ?? '';
            }
            $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/ticket/getticket';
            $params = ['type' => 'jsapi', 'access_token' => $accessToken];

            $result = HttpUtil::get($url, http_build_query($params));
            if (!$result || RespMsg::FAIL == $result->return_code) {
                return null;
            }

            $ticket = $result->return_msg->ticket;
            //缓存本次获取到的票据
            Yii::$app->cache->set('wx_' . $appId . '_ticket', $ticket, 6600);
        }
        return $ticket;
    }

    /**
     * 清空公众号接口调用配额<br>
     *
     * 每个公众号每个月有10次清零机会，包括在微信公众平台上的清零以及调用API进行清零
     * @param AppInfo $appInfo
     * @return RespMsg
     */
    public function clearAppQuota($appInfo)
    {
        $respMsg = $this->getComponentAccessToken();
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $respMsg = $appInfo->clearQuota($respMsg->return_msg['accessToken']);
        }
        return $respMsg;
    }

    /**
     * 为网页授权时生成请求CODE地址
     * @param $appId
     * @param $redirectUri
     * @param string $scope
     * @return RespMsg
     */
    public function getWebAuthorizeUri($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $respMsg = new RespMsg();
        $componentAppId = Yii::$app->params['wxConfig']['appId'];
        $reqCodeUrl = Yii::$app->params['wxConfig']['openUrl'] . '/connect/oauth2/authorize'
            . '?appid=' . $appId
            . '&redirect_uri=' . urlencode(Yii::$app->params['serviceDomain']['weiXinApiDomain'] . '/component/redirect')
            . '&component_appid=' . $componentAppId
            . '&response_type=code'
            . '&scope=' . $scope
            . '&state=' . urlencode($redirectUri)
            . '#wechat_redirect';
        $respMsg->return_msg['reqCodeUrl'] = $reqCodeUrl;
        return $respMsg;
    }

    /**
     * 为网页授权时生成请求CODE地址(只针对微分销)
     * @param $appId
     * @param $redirectUri
     * @param string $scope
     * @return RespMsg
     */
    public function getWebAuthorizeUriForFx($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $respMsg = new RespMsg();
        $componentAppId = Yii::$app->params['wxConfig']['appId'];
        $reqCodeUrl = Yii::$app->params['wxConfig']['openUrl'] . '/connect/oauth2/authorize'
            . '?appid=' . $appId
            . '&redirect_uri=' . urlencode(Yii::$app->params['serviceDomain']['weiXinApiDomain'] . '/component/redirect-for-fx')
            . '&component_appid=' . $componentAppId
            . '&response_type=code'
            . '&scope=' . $scope
            . '&state=' . $this->createShorturl(urldecode($redirectUri))
            . '#wechat_redirect';
        $respMsg->return_msg['reqCodeUrl'] = $reqCodeUrl;
        return $respMsg;
    }

    /**
     * 第四步：通过网页授权access_token获取用户基本信息（需授权作用域为snsapi_userinfo）
     * 如果网页授权作用域为snsapi_userinfo，则此时开发者可以通过access_token和openid拉取用户信息了。
     * @param $openId
     * @param $accessToken
     * @return RespMsg
     */
    public function getWebUserInfo($openId, $accessToken)
    {
        $respMsg = new RespMsg();
        $curl = new Curl();
        $curl->get(
            Yii::$app->params['wxConfig']['appUrl'] . '/sns/userinfo',
            [
                'access_token' => $accessToken,
                'openid' => $openId,
                'lang' => 'zh_CN',
            ]
        );
        $result = json_decode($curl->response);
        if ($curl->error || (isset($result->errcode) && $result->errcode != 0)) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = $curl->response;
            Yii::error('获取用户基本信息' . $curl->response, __METHOD__);
        } else {
            $respMsg->return_msg = $result;
        }
        $curl->close();
        return $respMsg;
    }

    /**
     * 检查是否存在该商家的商家获得的用户登录授权数据并返回
     * @param $appId
     * @param $redirctUrl
     * @param $wxid
     * @param $dataType
     * @return bool|mixed|string
     */
    public function checkUserInfoExist($appId, $redirctUrl, $wxid, $dataType)
    {
        $auth_flag = true;//是否需要重新授权
        //<p>第一步：判断cookie中的token是否存在</p>
        $cookie = Yii::$app->request->getCookies();
        if (isset($cookie["user_auth_data"]->value)) {
            $auth_data = $cookie["user_auth_data"]->value;
            $newSign = md5($auth_data['access_token'] . '&' . $auth_data['openid'] . '&' . Yii::$app->params['signKey']['apiSignKey']);
            if (isset($auth_data['sign']) && $newSign == $auth_data['sign']) {
                $accessToken = $auth_data['access_token'];
                $openId = $auth_data['openid'];
            }
        }
        //<p>第二步：cookie中的token过期，则判断refresh_token是否存在</p>
        if (empty($accessToken) || empty($openId)) {
            if (isset($cookie["user_refreshToken"]->value)) {
                $refreshData = $cookie["user_refreshToken"]->value;
                $newSign = md5($refreshData['refresh_token'] . '&' . Yii::$app->params['signKey']['voteSignKey']);
                if (isset($refreshData['sign']) && $newSign == $refreshData['sign']) {
                    //2.1、refreshToken未过期，则去代理平台刷新授权token
                    $resp = $this->refreshTokenFromApi($appId, $refreshData['refresh_token']);
                    if (!empty($resp)) {
                        $accessToken = $resp['return_msg']['access_token'];
                        $openId = $resp['return_msg']['openid'];
                    }
                }
            }
        }
        //<p>第三步：根据token获取用户数据后写入session，然后跳转回业务处理界面</p>
        if (!empty($accessToken) && !empty($openId)) {
            $result = $this->getUserDataFromApi($openId, $accessToken, $wxid);
            if ($result != false) {
                $auth_flag = false;//不需要授权了。
            }
        }
        //<p>第四步：以上步骤异常，则重新授权</p>
        if ($auth_flag == true) {
            $resp = $this->webAuthorizeFromApi($appId, $redirctUrl, $dataType);
            if (!empty($resp)) {
                return $resp;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * 从代理平台，用refreshToken来刷新token http://trac.idouzi.com/trac/idouzi/ticket/411
     * @param $appId
     * @param $refreshToken
     * @return mixed
     */
    private function refreshTokenFromApi($appId, $refreshToken)
    {
        $resp = $this->getWebPageRefreshToken($appId, $refreshToken);
        if (isset($resp['return_code']) && $resp['return_code'] == 'SUCCESS') {
            $signString = $resp['return_msg']['access_token'] . '&' . $resp['return_msg']['openid'];
            $resp['return_msg']['sign'] = md5($signString . '&' . Yii::$app->params['signKey']['apiSignKey']);
            $cookie = new Cookie(
                ['name' => 'user_auth_data',
                    'value' => json_encode($resp['return_msg']),
                    'expire' => time() + 60 * 60 * 2 - 600,
                ]);//有效期两个小时
            Yii::$app->response->cookies->add($cookie);
            Yii::$app->request->cookies['user_auth_data'] = $cookie;
        } else {
            $resp = '';
            Yii::warning('refresh access_token failed! ' . ',resp=' . json_encode($resp), __METHOD__);
        }
        return $resp;
    }

    /**
     * 获取用户基本信息，成功则写入session http://trac.idouzi.com/trac/idouzi/ticket/411
     * @param $openId
     * @param $accessToken
     * @return bool
     */
    public function getUserDataFromApi($openId, $accessToken, $wxid)
    {
        $result = false;
        $resp = $this->getWebUserInfo($openId, $accessToken);

        if (isset($resp['return_code']) && $resp['return_code'] == 'SUCCESS') {
            $_SESSION['oauth_info' . $wxid] = $resp['return_msg'];
            $result = json_encode($resp['return_msg']);
        } else {
            Yii::warning('get user data error! ' . ',resp=' . json_encode($resp), __METHOD__);
        }

        return $result;
    }

    /**
     * 向代理平台发起网页授权 http://trac.idouzi.com/trac/idouzi/ticket/411
     * @param $appId
     * @param $redirectUri
     * @param string $scope
     * @return mixed|string
     */
    private function webAuthorizeFromApi($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $resp = $this->getWebAuthorizeUri($appId, $redirectUri, $scope);
        if (isset($resp['return_code']) && $resp['return_code'] == 'SUCCESS') {
            $resp = $resp['return_msg']['reqCodeUrl'];
        } else {
            $resp = '';
        }
        return $resp;
    }

    /**
     * 保存链接至缓存
     * @param unknown $url
     * @return string|boolean
     */
    public function createShorturl($url)
    {
        $id = "shorturl_" . md5($url);
        $expire = 60 * 10;
        if (Yii::$app->cache->set($id, $url, $expire)) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 获取缓存中值
     * @param unknown $key
     * @return mixed|boolean|string
     */
    public function getShorturl($key)
    {
        return Yii::$app->cache->get($key);
    }

    /**
     * 清空公众号接口调用配额<br>
     *
     * 每个公众号每个月有10次清零机会，包括在微信公众平台上的清零以及调用API进行清零
     * @param ComponentInfo $componentInfo
     * @return RespMsg
     */
    public function clearComponentQuota($componentInfo)
    {
        $respMsg = $this->getComponentAccessToken();
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $respMsg = $componentInfo->clearQuota($respMsg->return_msg['accessToken']);
        }
        return $respMsg;
    }

    /**
     * 通过openId刷新网页授权token
     * @param string $openId
     * @param string $appId
     * @return null|string
     */
    public function refreshWebAccessTokenByOpenId(string $openId, string $appId)
    {
        //缓存中判断信息是否存在
        $tokenInfo = Yii::$app->cache->get('tokenInfo' . $openId . $appId);
        if (!$tokenInfo || !is_array($tokenInfo)) {
            //缓存没有则从数据库中获取
            $tokenInfo = WebUserAuthInfo::getRefreshTokenInfoByOpenIdAppId($openId, $appId);
        }
        //刷新token没有过期，去刷新token
        if (isset($tokenInfo['refreshTokenExpire']) && $tokenInfo['refreshTokenExpire'] > time()) {
            return $this->refreshAndSaveWebToken($appId, $tokenInfo);
        }

        return false;
    }

    /**
     * 刷新并保存token
     * @param $appId string 公众号id
     * @param $tokenInfo array token信息
     * @return bool|int
     */
    private function refreshAndSaveWebToken(string $appId, array $tokenInfo)
    {
        //刷新token并拿到openId
        $returnInfo = $this->getWebPageRefreshToken($appId, $tokenInfo['refreshToken']);
        //请求成功
        if ($returnInfo->return_code == RespMsg::SUCCESS) {
            //将数据重新保存
            $appCache['accessTokenExpire'] = time() + $returnInfo->return_msg->expires_in - 200;
            $appCache['accessToken'] = $returnInfo->return_msg->access_token;
            $appCache['refreshToken'] = $returnInfo->return_msg->refresh_token;
            $appCache['refreshTokenExpire'] = $tokenInfo['refreshTokenExpire'];

            //保存信息
            return $this->saveWebTokenInfo($appCache, $returnInfo->return_msg->openid, $appId);
        } else {//请求失败
            Yii::error('刷新token失败:' . json_encode($returnInfo->return_msg), __METHOD__);
        }
        return false;
    }

    /**
     * 通过缓存或数据库获取网页授权access_token
     * @param string $openId
     * @param string $appId 公众号id
     * @return null|string
     * @throws SystemException
     */
    public function getWebAccessTokenByCacheOrDb(string $openId, string $appId)
    {
        //缓存中判断信息是否存在
        $tokenInfo = Yii::$app->cache->get('tokenInfo' . $openId . $appId);
        //如果缓存中的网页授权信息没有过期
        if (!$tokenInfo || !is_array($tokenInfo)) {
            //缓存中的授权信息过期，查询数据库
            $tokenInfo = WebUserAuthInfo::getAccessToken($openId, $appId);
        }
        if (isset($tokenInfo['accessTokenExpire']) && $tokenInfo['accessTokenExpire'] > time()) {
            return $tokenInfo['accessToken'];
        }

        throw new SystemException('access_token过期或不存在');
    }

    /**
     * 保存网页授权token信息
     * @param array $info 具体信息
     * @param string $openId
     * @param string $appId 公众号id
     * @return bool|int
     * @throws SystemException
     */
    public function saveWebTokenInfo(array $info, string $openId, string $appId)
    {
        //更新缓存
        Yii::$app->cache->set('tokenInfo' . $openId . $appId, $info, $info['refreshTokenExpire']);
        //已存在数据则更新数据库
        if (WebUserAuthInfo::countByOpenIdAppId($openId, $appId)) {
            return WebUserAuthInfo::updateTokenInfo($info, $openId, $appId);
        } else {
            $model = new WebUserAuthInfo();
            if (!$model->load($info, '') || !$model->validate()) {
                Yii::error('校验新的用户网页授权信息有误：' . json_encode($model->getFirstErrors()), __METHOD__);
                throw new SystemException('授权失败，请重试');
            }
            return $model->insert();
        }
    }

    /**
     * 新增永久图文素材
     * @param string $supplierId 商家编号
     * @param array $data 要上传的图文数据
     * @return string
     * @throws SystemException
     */
    public function materialAddNews(string $supplierId, array $data): string
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/material/add_news',
            'access_token=' . $accessToken,
            StringUtil::packForJsonStr(json_encode(['articles' => $data]))
        );

        if ($resMsg->return_code == RespMsg::SUCCESS) {
            return $resMsg->return_msg->media_id;
        }

        throw new SystemException($resMsg->return_msg->errmsg);
    }

    /**
     * 根据商家编号查询绑定的公众号的访问token
     *
     * @param string $supplierId 商家编号
     * @return string 返回访问token
     * @throws SystemException
     */
    private function getAppAccessTokenBySupplierId(string $supplierId): string
    {
        $appInfoModel = AppInfo::find()->where(['wxId' => $supplierId])->one();
        if (!$appInfoModel) {
            Yii::error('商家' . $supplierId . '不存在对应的公众号', __METHOD__);
            throw new SystemException('商家' . $supplierId . '不存在对应的公众号');
        }

        $respMsg = $this->getAppAccessToken($appInfoModel);
        return $respMsg->return_msg['accessToken'] ?? null;
    }

    /**
     * 根据标签进行群发【订阅号与服务号认证后均可用】
     *
     * @param string $supplierId 商家编号
     * @param array $data
     * @return mixed <code>
     *
     * <code>
     * {
     * "errcode":0,
     * "errmsg":"send job submission success",
     * "msg_id":34182,
     * "msg_data_id": 206227730
     * }
     * </code>
     * @throws SystemException
     */
    public function messageMassSendAll(string $supplierId, array $data): array
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/message/mass/sendall',
            'access_token=' . $accessToken,
            json_encode([
                'filter' => [
                    'is_to_all' => $data['isToAll'],
                    'tag_id' => null,
                ],
                'mpnews' => [
                    'media_id' => $data['mediaId']
                ],
                'msgtype' => 'mpnews',
                'send_ignore_reprint' => $data['sendIgnoreReprint'],
            ]),
            true
        );

        if ($resMsg->return_code == RespMsg::SUCCESS) {
            $result = $resMsg->return_msg;
            $result['_id'] = $data['_id'];
            $result['articleRemoteId'] = $data['articleRemoteId'];
            return $result;
        }

        throw new SystemException($resMsg->return_msg['errmsg']);
    }

    /**
     * 开发者可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版。为了满足第三方平台开发者的需求，在保留对openID预览能力的同时，
     * 增加了对指定微信号发送预览的能力，但该能力每日调用次数有限制（100次），请勿滥用。
     *
     * @param string $supplierId 商家编号
     * @param string $toWXName 用户昵称
     * @param string $mediaId 用于群发的消息的media_id
     * @return mixed
     * @throws SystemException
     */
    public function messageMassPreview(string $supplierId, string $toWXName, string $mediaId)
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/message/mass/preview',
            'access_token=' . $accessToken,
            json_encode([
                'towxname' => $toWXName,
                'mpnews' => ['media_id' => $mediaId],
                'msgtype' => 'mpnews',
            ])
        );

        if ($resMsg->return_code == RespMsg::SUCCESS) {
            return $resMsg;
        }

        throw new SystemException($resMsg->return_msg->errmsg);
    }

    /**
     * 上传图文消息内的图片获取URL
     *
     * @param string $supplierId 商家编号
     * @param string $fileType 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @param string $url 远程文件访问地址
     * @return array ['url' => 'http:\/\/mmbiz.qpic.cn\/mmbiz_...'] 返回链接
     * @throws SystemException
     */
    public function mediaUploadImg(string $supplierId, string $fileType, string $url): array
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $filePath = HttpUtil::downloadRemoteFile($url, FileUtil::getSuffixNameByType($fileType));

        try {
            $response = HttpUtil::weiChatFormUpload(
                Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/media/uploadimg?access_token=' . $accessToken,
                [
                    'media' => new \CURLFile(realpath($filePath)),
                ]
            );
            return $response;
        } catch (\Exception $e) {
            throw new SystemException($e->getMessage());
        } finally {
            //不管如何都将删除文件，以控制本地临时文件容量。
            FileUtil::deleteFileByAbsolutePath($filePath);
        }
    }

    /**
     * 新增图片（image）类型永久素材
     *
     * @param string $supplierId 商家编号
     * @param string $fileType 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @param string $url 远程文件访问地址
     * @return array ['mediaId'=>'', 'mediaUrl'=>'']
     * @throws SystemException
     */
    public function materialAddMaterialForImage(string $supplierId, string $fileType, string $url): array
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $filePath = HttpUtil::downloadRemoteFile($url, FileUtil::getSuffixNameByType($fileType));

        try {
            $response = HttpUtil::weiChatFormUpload(
                Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/material/add_material?'
                . 'access_token=' . $accessToken . '&type=' . $fileType,
                [
                    'media' => new \CURLFile(realpath($filePath)),
                ]
            );

            return ['mediaId' => $response['media_id'], 'mediaUrl' => $response['url'] ?? null];
        } catch (\Exception $e) {
            throw new SystemException($e->getMessage());
        } finally {
            //不管如何都将删除文件，以控制本地临时文件容量。
            FileUtil::deleteFileByAbsolutePath($filePath);
        }
    }

    /**
     * 删除永久素材
     *
     * @param string $supplierId 商家编号
     * @param string $mediaId 要获取的素材的media_id
     * @return array
     * @throws SystemException
     */
    public function materialDelMaterial(string $supplierId, string $mediaId)
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/material/del_material',
            'access_token=' . $accessToken,
            json_encode(['media_id' => $mediaId,])
        );

        return $resMsg->return_msg;
    }

    /**
     * 修改永久图文素材
     *
     * @param string $supplierId 商家编号
     * @param int $index 文章位置索引
     * @param array $data 要修改的文章内容
     * @return array
     */
    public function materialUpdateNews(string $supplierId, int $index, array $data)
    {
        $accessToken = $this->getAppAccessTokenBySupplierId($supplierId);

        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/material/update_news',
            'access_token=' . $accessToken,
            StringUtil::packForJsonStr(json_encode([
                'media_id' => $data['mediaId'],
                'index' => $index,
                'articles' => [
                    'title' => $data['title'],
                    'thumb_media_id' => $data['thumbMediaId'],
                    'author' => $data['author'],
                    'digest' => $data['digest'] ?? '',
                    'show_cover_pic' => $data['showCoverPic'],
                    'content' => $data['content'],
                    'content_source_url' => $data['contentSourceUrl'] ?? '',
                ],
            ]))
        );

        return $resMsg->return_msg;
    }

    /**
     * 消息推送
     * @param array $pushData 推送的信息
     * @param string $templateId 模板id
     * @return mixed
     * @throws SystemException
     * @throws \Exception
     */
    public function pushMsg(array $pushData, string $templateId)
    {
        //先获取官方信息
        $appInfo = AppInfo::findOne(Yii::$app->params['officialAppId']);
        if (!$appInfo) {
            throw new SystemException('消息推送时，找不到官方公众号信息,appId:' . Yii::$app->params['officialAppId']);
        }
        //获取accesstoken
        $result = $this->getAppAccessToken($appInfo);
        //获取出错则返回异常
        if ($result->return_code === RespMsg::FAIL) {
            throw new \Exception(
                is_string($result->return_msg) ? $result->return_msg : json_encode($result->return_msg)
            );
        }
        $pushData['template_id'] = $templateId;
        $resMsg = HttpUtil::post(
            Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/message/template/send',
            'access_token=' . $result->return_msg['accessToken'],
            json_encode($pushData)
        );
        if ($resMsg->return_code === RespMsg::FAIL) {
            throw new \Exception(
                is_string($resMsg->return_msg) ? $resMsg->return_msg : json_encode($resMsg->return_msg)
            );
        }

        return $resMsg->return_msg;
    }
}
