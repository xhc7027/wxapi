<?php

namespace app\controllers;

use app\behaviors\MonitorBehavior;
use app\behaviors\SupplierAccessFilter;
use app\commons\HttpUtil;
use app\exceptions\SystemException;
use app\models\AppInfo;
use app\models\AppShareConf;
use app\models\RespMsg;
use app\models\vo\AddMaterialForm;
use app\models\WebUserAuthInfo;
use app\services\AppChooseServices;
use app\services\DataService;
use app\services\WeiXinService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use Curl\Curl;
use app\commons\FileUtil;

/**
 * 面向内部调用的高层外观接口
 * @package app\controllers
 */
class FacadeController extends Controller
{
    /**
     * @var bool 关闭CSRF验证
     */
    public $enableCsrfValidation = false;

    /**
     * 在每个Action执行前后作业务处理
     * @return array
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'binding' => ['get'],
                    'app-info' => ['get'],
                    'access-token' => ['get'],
                    'openid' => ['get'],
                    'user-info' => ['get'],
                    'web-page' => ['get'],
                    'material-add-material' => ['post', 'options'],
                ],
            ],
            'monitor' => [
                'class' => MonitorBehavior::className()
            ],
            'access' => [
                'class' => SupplierAccessFilter::className(),
                'actions' => ['bind-page', 'openid', 'web-page', 'app-info-clear-quota', 'get-accesstoken', 'get-supplier-nick-name', ' get-article-list', 'get-wx-info', 'get-app-info', 'get-media-info']
            ],
            'apiAccess' => [
                'class' => 'app\behaviors\ApiAccessFilter',
                'actions' => ['material-add-material'],
            ],
            'postAccess' => [
                'class' => 'app\behaviors\PostAccessFilter',
                'actions' => ['send-msg']
            ],
        ];
    }

    /**
     * 一键绑定跳转<br>
     *
     * 商家中心点击“绑定”后跳转到此页面，由页面获取绑定公众号信息，之后再跳转到商家中心并带上AppId
     * @return string
     */
    public function actionBinding($wxid)
    {
        $appId = Yii::$app->params['wxConfig']['appId'];
        $respMsg = Yii::$app->weiXinService->getComponentPreAuthCode();
        if ($respMsg->return_code === RespMsg::SUCCESS) {
            $url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage'
                . '?component_appid=' . $appId
                . '&pre_auth_code=' . $respMsg->return_msg['preAuthCode']
                . '&redirect_uri=' . urlencode(Yii::$app->params['serviceDomain']['weiXinApiDomain']
                    . '/component/component-login-redirect?wxid=' . $wxid
                );
            return $this->redirect($url);
        }
        return $respMsg->toJsonStr();
    }

    /**
     * 一键绑定页面
     * @return string
     */
    public function actionBindPage($wxid)
    {
        return $this->renderPartial('binding', ['wxid' => $wxid]);
    }

    /**
     * 获取公众号状态
     * @param string $appId 公众号appId
     * @return string []
     */
    public function actionAppInfo($appId)
    {
        $respMsg = new RespMsg();

        $model = AppInfo::findOne($appId);
        if ($model) {
            //如果没有此公众号基本信息则重新获取
            if (!$model->userName || !$model->serviceTypeInfo) {
                $respMsg = Yii::$app->weiXinService->getComponentAccessToken();
                if ($respMsg->return_code === RespMsg::SUCCESS) {
                    $model->getAuthorizeInfo($respMsg->return_msg['accessToken']);
                    $model = AppInfo::findOne($appId);
                } else {
                    return $respMsg->toJsonStr();
                }
            }

            $respMsg->return_msg['appId'] = $model->appId;
            $respMsg->return_msg['nickName'] = $model->nickName;
            $respMsg->return_msg['headImg'] = $model->headImg;
            $serviceType = $model->serviceTypeInfo;
            if ($serviceType === 0 || $serviceType === 1) {
                $serviceType = 0;
            }
            $respMsg->return_msg['serviceType'] = $serviceType;
            $verifyType = $model->verifyTypeInfo;
            if ($verifyType === -1) {
                $verifyType = 0;
            } else {
                $verifyType = 1;
            }
            $respMsg->return_msg['verifyType'] = $verifyType;
            $respMsg->return_msg['alias'] = $model->alias;
            $respMsg->return_msg['qrCodeUrl'] = $model->qrcodeUrl;
            $authStatus = $model->infoType;
            if ($authStatus == 'unauthorized' || empty($model->refreshToken)) {
                $authStatus = 0;
            } else {
                $authStatus = 1;
            }
            $respMsg->return_msg['authStatus'] = $authStatus;
            //获取此公众号访问令牌
            $tmpRespMsg = $this->getAccessToken($model);
            if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
                $accessToken = $tmpRespMsg->return_msg['accessToken'];
                $expiresIn = $tmpRespMsg->return_msg['expiresIn'];
                $respMsg->return_msg['accessToken'] = $accessToken;
                $respMsg->return_msg['expiresIn'] = $expiresIn;
            } else {
                $respMsg = $tmpRespMsg;
            }
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于 ' . $appId . ' 的信息。';
        }

        return $respMsg->toJsonStr();
    }

    /**
     * 获取指定公众号对应的访问令牌
     * @param string $appId 公众号appId
     * @return string []
     */
    public function actionAccessToken($appId)
    {
        $respMsg = new RespMsg();
        $appInfo = AppInfo::findOne($appId);
        if ($appInfo) {
            $respMsg = $this->getAccessToken($appInfo);
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $appId . '的信息。';
        }

        return $respMsg->toJsonStr();
    }

    /**
     *
     * @param AppInfo $appInfo 公众号
     * @return RespMsg
     */
    private function getAccessToken($appInfo)
    {
        $respMsg = new RespMsg();
        if ($appInfo) {
            if (WeiXinService::UNAUTHORIZED != $appInfo->infoType) {//检测公众号授权状态
                $tmpRespMsg = Yii::$app->weiXinService->getAppAccessToken($appInfo);
                if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
                    if (isset($tmpRespMsg->return_msg['accessToken'])) {
                        $respMsg->return_msg['accessToken'] = $tmpRespMsg->return_msg['accessToken'];
                    }
                    if (isset($tmpRespMsg->return_msg['expiresIn'])) {
                        $respMsg->return_msg['expiresIn'] = $tmpRespMsg->return_msg['expiresIn'];
                    }
                    if (isset($tmpRespMsg->return_msg['authorizationCode'])) {
                        $respMsg->return_msg['authorizationCode'] = $tmpRespMsg->return_msg['authorizationCode'];
                    }
                    if (isset($tmpRespMsg->return_msg['authorizationCodeExpiredTime'])) {
                        $respMsg->return_msg['authorizationCodeExpiredTime'] = $tmpRespMsg->return_msg['authorizationCodeExpiredTime'];
                    }
                } else {
                    $respMsg->return_code = RespMsg::FAIL;
                    $respMsg->return_msg = $tmpRespMsg->return_msg;
                }
            } else {
                $respMsg->return_code = RespMsg::FAIL;
                $respMsg->return_msg = '没有得到公众号' . $appInfo->appId . '的授权。';
            }
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $appInfo->appId . '的信息。';
        }

        return $respMsg;
    }

    /**
     * 代公众号发起网页授权<br>
     * 为了确保在获得网页授权链接最短时间内响应，这里并不对appId做验证。
     *
     * @param string $appId 公众号appId
     * @param string $redirectUri 重定向地址，服务开发方的回调地址
     * @param string $scope 授权作用域(snsapi_base和snsapi_userinfo)，拥有多个作用域用逗号（,）分隔
     * @return string 返回获取code的连接地址
     */
    public function actionWebAuthorize($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $respMsg = Yii::$app->weiXinService->getWebAuthorizeUri($appId, $redirectUri, $scope);
        return $respMsg->toJsonStr();
    }

    /**
     * @param $appId
     * @param $redirectUri
     * @param string $scope
     * @return
     * <code>
     * {
     *  "return_code" : "SUCCESS",
     *   "return_msg" : {
     *      "code" : accessToken/redirectUrl,//如果是accessToken 则msg数据是
     *               数组['access_token', 'openid']，反之，是跳转链接
     *      "msg" : "xxxxxxxx"
     *   }
     * }
     * </code
     */
    public function actionGetWebToken($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $respMsg = new RespMsg();
        // 选择实际调用的模型
        $appModel = new AppChooseServices($appId, 'userManagementAuthorize');
        $appId = $appModel->getAppId();
        $accessToken = Yii::$app->weiXinService->getWebAccessTokenByCacheOrDb($appId);
        //如果获取access_token成功
        if ($accessToken) {
            $respMsg->return_msg['code'] = 'accessToken';
            $respMsg->return_msg['msg'] = $accessToken;
        } else {
            //获取access_token失败则重新授权
            $msg = Yii::$app->weiXinService->getWebAuthorizeUri($appId, $redirectUri, $scope);
            $respMsg->return_msg['code'] = 'redirectUrl';
            $respMsg->return_msg['msg'] = $msg->return_msg['reqCodeUrl'];
        }

        return $respMsg->toJsonStr();

    }

    /**
     * @param $appId
     * @param $redirectUri
     * @param string $scope
     * @return
     * <code>
     * {
     *  "return_code" : "SUCCESS",
     *   "return_msg" : {
     *      "code" : accessToken/redirectUrl,//如果是accessToken 则msg数据是
     *               数组['access_token', 'openid']，反之，是跳转链接
     *      "msg" : "xxxxxxxx"
     *   }
     * }
     * </code
     */
    public function actionToGetWebToken($appId, $redirectUri, $scope = 'snsapi_base')
    {
        try {
            $queryAppId = $appId;
            Yii::$app->session->set('queryAppId', $queryAppId);
            // 选择实际调用的模型
            $appModel = new AppChooseServices($appId, 'userManagementAuthorize');
            $appId = $appModel->getAppId();
            //初始化token信息是否可用状态为false
            $tokenExist = false;
            //get参数有openId则找该用户的刷新token信息
            if ($openId = Yii::$app->request->get('openId')) {
                //尝试刷新网页授权token
                $tokenExist = Yii::$app->weiXinService->refreshWebAccessTokenByOpenId($openId, $appId, $queryAppId);
            }
            Yii::$app->session->set('webRedirectUri', $redirectUri);
            Yii::$app->session->set('webAccessTokenAppId', $appId);
            //如果刷新成功
            if ($tokenExist) {
                //回到原来业务
                $redirectUrl = strpos($redirectUri, '?') !== false ? $redirectUri . '&openid=' . $openId
                    : $redirectUri . '?' . '&openid=' . $openId;
                return $this->redirect($redirectUrl);
            }
            //没有刷新成功则重新授权
            $msg = Yii::$app->weiXinService->getWebAuthorizeUri(
                $appId,
                Yii::$app->params['serviceDomain']['weiXinApiDomain'] . '/facade/get-open-id-and-access-token?',
                $scope);

            return $this->redirect($msg->return_msg['reqCodeUrl']);
        } catch (\Exception $e) {
            Yii::error('跳转到网页授权错误:' . $e->getMessage(), __METHOD__);
            return $this->redirect($redirectUri);
        }
    }

    /**
     * 通过code获取openid和access_token
     * @return array|string|\yii\web\Response
     */
    public function actionGetOpenIdAndAccessToken()
    {
        $resp = new RespMsg(['return_code' => RespMsg::FAIL]);
        try {
            //模型校验
            $model = new WebUserAuthInfo();
            $model->openId = Yii::$app->request->get('openid');//用户OPENID
            $model->accessTokenExpire = time() + Yii::$app->request->get('expire_in', 6600);
            $model->accessToken = Yii::$app->request->get('access_token');//用户访问令牌
            $model->refreshToken = Yii::$app->request->get('refresh_token');//刷新访问令牌token
            $model->refreshTokenExpire = time() + 60 * 60 * 24 * 14;
            if (!$model->validate()) {
                return "参数错误，网页授权失败，请重试~";
            }

            //更新缓存
            $model->appId = Yii::$app->session->get('webAccessTokenAppId');
            $model->queryAppId = Yii::$app->session->get('queryAppId');
            if ($model->appId === null || $model->queryAppId === null) {
                throw new SystemException('session中的公众号id不存在');
            }
            //更新数据库和缓存
            Yii::$app->weiXinService->saveWebTokenInfo($model, $model->openId, $model->appId, $model->queryAppId);

            //回到原来业务
            $redirectUrl = Yii::$app->session->get('webRedirectUri');
            $redirectUrl = strpos($redirectUrl, '?') !== false ? $redirectUrl . '&'
                . http_build_query(Yii::$app->request->get()) :
                $redirectUrl . '?' . http_build_query(Yii::$app->request->get());
            return $this->redirect($redirectUrl);
        } catch (\TypeError $e) {
            Yii::warning('获取用户信息出现TypeError：' . $e->getMessage(), __METHOD__);
            $resp->return_msg = '获取用户信息发生类型错误问题';
        } catch (SystemException $e) {
            Yii::warning('获取用户信息失败：' . $e->getMessage(), __METHOD__);
            $resp->return_msg = $e->getMessage();
        } catch (\Exception $e) {
            Yii::warning('获取用户信息失败：' . $e->getMessage(), __METHOD__);
            $resp->return_msg = '获取用户信息失败';
        }

        return $resp->return_msg;
    }

    /**
     * 通过网页授权access_token获取用户基本信息（需授权作用域为snsapi_userinfo）<br>
     * 正常情况下将返回下面数据：<br>
     * <code>
     * {
     *   "return_code":"SUCCESS",
     *   "return_msg":
     *     {
     *       "openid":"ok8uTuK3dBKSPXONiE7Sxj7MKFjU",
     *       "nickname":"allen",
     *       "sex":1,
     *       "language":"zh_CN",
     *       "city":"\u6df1\u5733",
     *       "province":"\u5e7f\u4e1c",
     *       "country":"\u4e2d\u56fd",
     *       "headimgurl":"http:\/\/wx.qlogo.cn\/mmopen\/dOdribRiaxYucLqibCYSrt\/0",
     *       "privilege":[]
     *     }
     * }
     * </code>
     * @param string $openId
     * @param string $appId
     * @return string json
     */
    public function actionGetWebUserInfo($openId, $appId)
    {
        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        try {
            $queryAppId = $appId;
            $appModel = new AppChooseServices($appId, 'userManagementAuthorize');
            $appId = $appModel->getAppId();
            $accessToken = Yii::$app->weiXinService->getWebAccessTokenByCacheOrDb($openId, $appId, $queryAppId);
            $respMsg = Yii::$app->weiXinService->getWebUserInfo($openId, $accessToken);
        } catch (\Exception $e) {
            $respMsg->return_msg = $e->getMessage();
        }

        return $respMsg->toJsonStr();
    }

    /**
     * 代公众号发起网页授权<br>(只针对微分销)
     * 为了确保在获得网页授权链接最短时间内响应，这里并不对appId做验证。
     *
     * @param string $appId 公众号appId
     * @param string $redirectUri 重定向地址，服务开发方的回调地址
     * @param string $scope 授权作用域(snsapi_base和snsapi_userinfo)，拥有多个作用域用逗号（,）分隔
     * @return string 返回获取code的连接地址
     */
    public function actionWebAuthorizeForFx($appId, $redirectUri, $scope = 'snsapi_base')
    {
        $respMsg = Yii::$app->weiXinService->getWebAuthorizeUriForFx($appId, $redirectUri, $scope);
        return $respMsg->toJsonStr();
    }

    /**
     * 刷新access_token
     * @param $appId
     * @param $refreshToken
     * @return string json
     */
    public function actionWebRefreshToken($appId, $refreshToken)
    {
        $respMsg = Yii::$app->weiXinService->getWebPageRefreshToken($appId, $refreshToken);
        return $respMsg->toJsonStr();
    }

    /**
     * 通过网页授权access_token获取用户基本信息（需授权作用域为snsapi_userinfo）<br>
     * 正常情况下将返回下面数据：<br>
     * <code>
     * {
     *   "return_code":"SUCCESS",
     *   "return_msg":
     *     {
     *       "openid":"ok8uTuK3dBKSPXONiE7Sxj7MKFjU",
     *       "nickname":"allen",
     *       "sex":1,
     *       "language":"zh_CN",
     *       "city":"\u6df1\u5733",
     *       "province":"\u5e7f\u4e1c",
     *       "country":"\u4e2d\u56fd",
     *       "headimgurl":"http:\/\/wx.qlogo.cn\/mmopen\/dOdribRiaxYucLqibCYSrt\/0",
     *       "privilege":[]
     *     }
     * }
     * </code>
     * @param string $openId
     * @param string $accessToken
     * @return string json
     */
    public function actionWebUserInfo($openId, $accessToken)
    {
        $respMsg = Yii::$app->weiXinService->getWebUserInfo($openId, $accessToken);
        return $respMsg->toJsonStr();
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
     * @param $appid 公众号appId
     * @param $url 用户访问的当前网页地址
     * @return string
     */
    public function actionWebPage($appid, $url)
    {
        $respMsg = new RespMsg();
        //1.如果请求的方式不是appId或wxId中的一个
        if (!in_array($type = Yii::$app->request->get('type', 'appId'), array('appId', 'wxId'))) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '请求类型有误';
            return $respMsg;
        }

        $signPackage = Yii::$app->weiXinService->getSignPackage4Js($url, $appid, $type);

        $respMsg->return_msg = $signPackage;
        return $respMsg->toJsonStr();
    }

    /**
     * 从微信接口获取数据统计数据<br>
     * @param string $appId
     * @param string $beginDate
     * @param string $endDate
     * @return string
     */
    public function actionDataStatistics($appId, $beginDate, $endDate)
    {
        $respMsg = new RespMsg();

        $appInfo = AppInfo::findOne($appId);
        if ($appInfo) {
            $dataService = new DataService($appInfo);
            $respMsg = $dataService->getDataStatistics($beginDate, $endDate);
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $appId . '的信息。';
        }

        return $respMsg->toJsonStr();
    }

    /**
     * 获取第三方公众号信息
     */
    public function actionComponentInfo()
    {
        $respMsg = Yii::$app->weiXinService->getComponentAccessToken();
        return $respMsg->toJsonStr();
    }

    /**
     * 页面跨域获取微信jsdk配置
     * @return string
     */
    public function actionSignPackage()
    {
        $callback = Yii::$app->request->get('callback');
        $url = base64_decode(Yii::$app->request->get('url'));
        $wxid = Yii::$app->request->get('wxid', '');
        $appId = null;
        if ($wxid) {
            $appId = AppInfo::find()->select(['appId'])
                ->where(['wxid' => $wxid, 'verifyTypeInfo' => 0, 'serviceTypeInfo' => 2, 'infoType' => 'authorized'])//认证服务号
                ->orderBy('twoUpdatedAt DESC')
                ->asArray()
                ->scalar();
        }
        $signPackage = Yii::$app->weiXinService->getSignPackage4Js($url, $appId);
        $result = json_encode(
            [
                "status" => 1,
                "signpackage" => json_encode($signPackage)
            ]
        );
        return $callback . "($result)";
    }

    /**
     * 给网页用户服务提供获取用户信息的方法
     */
    public function actionGetUserData()
    {
        $wxId = Yii::$app->request->get('wxid');
        if (!$wxId) {
            $wxId = Yii::$app->request->post('wxid');
        }
        if (!in_array($dataType = Yii::$app->request->get('userDataType'), array('snsapi_userinfo', 'snsapi_base'))) {
            exit("获取用户授权类型有误，请重试~");
        }
        // 选择实际调用的模型
        $appModel = new AppChooseServices($wxId, 'userManagementAuthorize', 'wxId');
        $appId = $appModel->getAppId();

        $redirctUrl = Yii::$app->request->get('redirctUrl');//授权回调地址
        if ($resp = Yii::$app->weiXinService->checkUserInfoExist($appId, $redirctUrl, $wxId, $dataType)) {
            $this->redirect($resp);
        } else {
            exit("获取用户授权失败，请重试~");
        }
        return true;
    }


    /**
     * 根据wxid获取授权方的appid
     * @param int $wxid
     * @return null
     */
    private static function getAppidByWxid($wxid)
    {
        $url = Yii::$app->params['serviceDomain']['iDouZiDomain'] . '/supplier/api/getAppidByWxid';
        $params = [
            'apikey' => '839',
            'wxid' => $wxid,
        ];
        $resp = HttpUtil::get($url, http_build_query($params));
        return $resp->return_msg->return_msg;
    }

    /**
     *  根据wxid
     * @param null $appId
     * @return null|string
     */
    public function actionGetAccesstoken($appId)
    {
        $respMsg = new RespMsg();
        $flag = false;
        $appInfo = AppInfo::find()->where(['appId' => $appId, 'verifyTypeInfo' => 0, 'serviceTypeInfo' => 2, 'infoType' => 'authorized'])
            ->orderBy('twoUpdatedAt DESC')->one();
        if ($appInfo) {
            $respMsg = $this->getAccessToken($appInfo);
            if ($respMsg->return_code == RespMsg::SUCCESS) {
                $flag = true;
            }
        }

        if (!$flag) {
            $model = (new AppShareConf())->getShareInfo();//TODO 1.轮询获取带分享账号
            $respMsg->return_msg = $model->getAccessToken();
        }
        return $respMsg;
    }

    /**
     * 对公众号的所有API调用（包括第三方代公众号调用）次数进行清零
     * @param string $id 公众号AppId
     * @return string
     */
    public function actionAppInfoClearQuota($id)
    {
        $respMsg = new RespMsg();
        $model = AppInfo::findOne($id);
        if (!$model) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '没有找到关于' . $id . '的信息。';
        }

        $respMsg = Yii::$app->weiXinService->clearAppQuota($model);
        return $respMsg->toJsonStr();
    }

    /**
     * 获取商家的公众号信息
     * @param $wxid
     * @return RespMsg
     */
    public function actionGetAppInfo($wxid)
    {
        $model = AppInfo::find()->select(['headImg', 'nickName', 'verifyTypeInfo', 'qrcodeUrl', 'wxId as id', 'appId', 'serviceTypeInfo'])
            ->where(['wxId' => $wxid])->asArray()->one();
        if (!$model) {
            return new RespMsg(['return_code' => RespMsg::FAIL]);
        }
        return new RespMsg(['return_msg' => $model]);
    }

    /**
     * 获取商家昵称
     * @return RespMsg
     */
    public function actionGetSupplierNickName()
    {
        $res = [];
        $data = AppInfo::find()->select('wxId,nickName')->where(['wxId' => Yii::$app->request->post('supplierIds')])
            ->asArray()->all();
        if (!$data) {
            return new RespMsg(['return_msg' => $res]);
        }
        foreach ($data as $item) {
            $res[$item['wxId']] = $item['nickName'];
        }
        return new RespMsg(['return_msg' => $res]);
    }

    /**
     * 新增其他类型永久素材，媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     */
    public function actionMaterialAddMaterial()
    {
        $respMsg = new RespMsg();

        try {
            $form = new AddMaterialForm();
            $form->load(Yii::$app->request->post(), '');
            if (!$form->validate()) {
                Yii::warning('调用新增其他类型永久素材接口错误：' . json_encode($form->getFirstErrors()), __METHOD__);
                throw new SystemException('非法参数。');
            }

            $respMsg->return_msg = Yii::$app->weiXinService->materialAddMaterialForImage(
                Yii::$app->session->get(Yii::$app->params['constant']['sessionName']['supplierId']),
                $form->tmpName,
                $form->type
            );
        } catch (\Exception $e) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = $e->getMessage();
        }

        return $respMsg;
    }

    /****************工作通-idouzi客服回复消息 start*******************/

    /**
     * 客服回复粉丝信息 //https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=ACCESS_TOKEN
     * 测试wxid=11721, openid = okmGDuCI5WUd31MiRddyHdRKkcwk
     */
    public function actionSendMsg()
    {
        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        $wxId = Yii::$app->request->post('wxid');
        $msgType = Yii::$app->request->post('msgType');
        $openId = Yii::$app->request->post('toUser');
        if (!$wxId) {
            $respMsg->return_msg = 'wxid不能为空';
            return $respMsg;
        }
        $sendData = [
            'touser' => $openId,
            'msgtype' => $msgType
        ];
        //获取商家的token
        $tmpRespMsg = $this->getWxidToken($wxId);
        if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
            $accessToken = $tmpRespMsg->return_msg['accessToken'];
            $message = Yii::$app->request->post($msgType);
            //post动态数据转化成客服消息
            $messageContent = $this->getIdouziMessage($msgType, $message, $accessToken) ?? null;
            if (!$messageContent) {
                $respMsg->return_msg = 'fail: 不支持的格式';
                return $respMsg;
            }
            $sendData[$msgType] = $messageContent;
            $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/message/custom/send';
            $resp = HttpUtil::post($url,
                'access_token=' . $accessToken,
                json_encode($sendData, JSON_UNESCAPED_UNICODE)
            );
            if ($resp->return_code === RespMsg::SUCCESS) {
                $respMsg->return_code = RespMsg::SUCCESS;
                $respMsg->return_msg = $resp->return_msg;
            } else {
                //微信返回码文字说明
                $respMsg->return_msg = $this->getWxErrorMsg($resp->return_msg->errcode, '发送消息失败,请重试');
            }
        } else {
            $respMsg = $tmpRespMsg;
        }
        return $respMsg;
    }

    /**
     * 根据openid获取粉丝基本信息
     */
    public function actionGetWxInfo()
    {
        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        $wxId = Yii::$app->request->get('wxid');
        $openId = Yii::$app->request->get('openid');
        if ($wxId) {
            //获取商家token
            $tmpRespMsg = $this->getWxidToken($wxId);
            if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
                $accessToken = $tmpRespMsg->return_msg['accessToken'];
                $params = [
                    'access_token' => $accessToken,
                    'openid' => $openId,
                    'lang' => 'zh_CN',
                ];
                $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/user/info';
                $resp = HttpUtil::get($url, http_build_query($params));
                if ($resp->return_code === RespMsg::SUCCESS) {
                    $respMsg->return_code = RespMsg::SUCCESS;
                    $respMsg->return_msg = $resp->return_msg;
                } else {
                    $respMsg->return_msg = '获取粉丝信息失败';
                }
            } else {
                $respMsg->return_msg = '获取粉丝信息失败';
            }
        } else {
            $respMsg->return_msg = '获取粉丝信息失败';
        }
        return $respMsg->toJsonStr();
    }

    /*
     * 根据wxid获取公众号的图文列表 get-article-list
     * 测试appid wx07618f660579cf07
     * 默认获取news图文素材
     */
    public function actionGetArticleList()
    {
        $respMsg = new RespMsg(['return_code' => RespMsg::FAIL]);
        $wxId = Yii::$app->request->get('wxid');
        $count = Yii::$app->request->get('count', 20);
        $offset = Yii::$app->request->get('offset', 0);
        //获取商家token
        $tmpRespMsg = $this->getWxidToken($wxId);
        if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
            $accessToken = $tmpRespMsg->return_msg['accessToken'];
            $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/material/batchget_material';
            $resp = HttpUtil::post($url,
                'access_token=' . $accessToken,
                json_encode(['count' => $count, 'offset' => $offset, 'type' => 'news'])
            );
            if ($resp->return_code === RespMsg::SUCCESS) {
                $respMsgList = $resp->return_msg;
                /***去除图文里面的content内容 start***/
                foreach ($respMsgList->item as &$v) {
                    foreach ($v->content->news_item as &$val) {
                        unset($val->content);
                    }
                }
                /***去除图文里面的content内容 end***/
                $respMsg->return_code = RespMsg::SUCCESS;
                $respMsg->return_msg = $respMsgList;
            } else {
                $respMsg->return_msg = '获取图文列表失败';
            }
        } else {
            $respMsg->return_msg = '获取图文列表失败';
        }
        return $respMsg->toJsonStr();
    }

    /**
     * 通过微信素材mediaid获取素材内容上传到工作通cos
     */
    public function actionGetMediaInfo()
    {
        $respMsg = new RespMsg();
        $wxId = Yii::$app->request->get('wxid');
        $mediaId = Yii::$app->request->get('mediaid');
        $fileSuffixName = Yii::$app->request->get('suffix', 'png');
        //获取商家token
        $tmpRespMsg = $this->getWxidToken($wxId);
        if ($tmpRespMsg->return_code === RespMsg::SUCCESS) {
            $accessToken = $tmpRespMsg->return_msg['accessToken'];
            $params = [
                'access_token' => $accessToken,
                'media_id' => $mediaId,
            ];
            $url = Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/media/get?' . http_build_query($params);
            //下载素材文件到本地临时文件
            $filePath = HttpUtil::downloadRemoteFile($url, '.' . $fileSuffixName);
            //上传本地临时文件到工作通cos
            $cosPath = $this->upFile($filePath, $fileSuffixName);
            if ($cosPath) {
                $respMsg->return_msg = $cosPath;
            } else {
                $respMsg->return_code = RespMsg::FAIL;
                $respMsg->return_msg = '素材获取失败';
            }
        } else {
            $respMsg = $tmpRespMsg;
        }
        return $respMsg->toJsonStr();
    }

    /**
     * 获取idouzi绑定工作通的账号
     *
     */
    public function actionGetWxBindIdouzi()
    {
        $wxId = Yii::$app->request->get('wxid');
        //通过工作通获取绑定商家id集合
        $resp = $this->getIdouziApi([
            'act' => 46,
            'wxid' => $wxId,
            'page_size' => Yii::$app->params['jobchatApiUrl']
        ]);
        $respMsg = new RespMsg();
        if (!$resp) {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '获取失败';
        }
        if ($resp['is_ok'] == 1) {
            $respMsg->return_msg = $resp['data']['serv_data'];
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = $resp['info'];
        }
        return $respMsg;
    }

    /**
     * 微信返回错误发解析
     *
     */
    private function getWxErrorMsg($errCode, $msg = '')
    {
        switch ($errCode) {
            case '45047':
                $errorMsg = '客服接口下行条数超过上限';
                break;
            case '45015':
                $errorMsg = '回复时间超过限制';
                break;
            default:
                $errorMsg = $msg;
                break;
        }
        return $errorMsg;
    }

    /**
     *  根据商家id获取微信token
     * @param number $wxId 商家id
     * @return object
     */
    private function getWxidToken($wxId)
    {
        $appInfo = AppInfo::find()->select(['appId', 'wxId', 'refreshToken', 'accessToken', 'infoType', 'zeroUpdatedAt', 'authorizationCode', 'authorizationCodeExpiredTime'])->where(['wxId' => $wxId])->one();
        $respMsg = new RespMsg();
        if ($appInfo) {
            $respMsg = $this->getAccessToken($appInfo);
        } else {
            $respMsg->return_code = RespMsg::FAIL;
            $respMsg->return_msg = '未找到公众号信息';
        }
        return $respMsg;
    }

    /**
     *  客服消息发送消息动态参数获取
     * @param string $msgType 发送消息类型 image text mpnews voice等
     * @param string $data 工作通post过来的动态数据
     * @param string $accessToken 微信token
     * @return object
     */
    private function getIdouziMessage($msgType, $data, $accessToken)
    {
        if (in_array($msgType, ['text', 'mpnews'])) {
            if (gettype($data) == 'string') {
                $data = json_decode($data, true);
            }
            return $data;
        }
        if (in_array($msgType, ['image', 'voice'])) {
            $cosPath = str_replace('\\', '', $data);
            if ($msgType == 'image') {
                $Suffix = '.png';
            } else {
                $Suffix = '.' . pathinfo($cosPath, PATHINFO_EXTENSION);
                //获取工作通cos签名
                $cosConfig = $this->getCosConfig();
                if (!$cosConfig) {
                    return null;
                }
                $cosPath .= '?sign=' . $cosConfig['sign'];
            }
            //下载cos文件到本地临时文件
            $filePath = HttpUtil::downloadRemoteFile($cosPath, $Suffix);
            try {
                //上传临时输出，并返回临时素材id
                $response = HttpUtil::weiChatFormUpload(
                    Yii::$app->params['wxConfig']['appUrl'] . '/cgi-bin/media/upload?'
                    . 'access_token=' . $accessToken . '&type=' . $msgType,
                    [
                        'media' => new \CURLFile(realpath($filePath)),
                    ]
                );
                return ['media_id' => $response['media_id']];
            } catch (\Exception $e) {
                throw new SystemException($e->getMessage());
            } finally {
                //不管如何都将删除文件，以控制本地临时文件容量。
                FileUtil::deleteFileByAbsolutePath($filePath);
            }
        }
        return null;
    }

    /**
     *  腾讯云上传文件和图片到工作通
     * @param string $filePath 文件的临时地址
     * @param string $type 文件后缀名
     * @return null|array 返回cos地址以及部分扩展信息
     */
    private function upFile($filePath, $type = 'png')
    {
        $cosConfig = $this->getCosConfig($type == 'png' ? 'img' : 'file');
        if (!$cosConfig || !$filePath) {
            return null;
        }
        try {
            //组合cos上传文件/图片数据
            $url = $type != 'png' ? $cosConfig['url'] . urlencode(md5_file($filePath) . '.' . $type) : $cosConfig['url'];
            $fileContent = $type != 'png' ? 'fileContent' : 'FileContent';
            if (function_exists('curl_file_create')) {
                $data[$fileContent] = curl_file_create(realpath($filePath));
            } else {
                $data[$fileContent] = '@' . realpath($filePath);
            }
            if ($type != 'png') {
                $data['op'] = 'upload';
                $data['insertOnly'] = 1;
            }
            $req = [
                'url' => 'https:' . $url,
                'method' => 'post',
                'timeout' => 10,
                'data' => $data,
                'header' => [
                    'Authorization:QCloud ' . $cosConfig['sign'],
                ]
            ];
            //cos上传文件curl请求
            $response = $this->cosSend($req);
            $returnCode = json_decode($response, true);
            if ($returnCode['code'] == 0) {
                $return_msg['cosPath'] = $returnCode['data']['access_url'] ?? $returnCode['data']['download_url'];
                $return_msg['size'] = filesize($filePath);
                if ($type == 'png') {
                    $return_msg['info'] = $returnCode['data']['info'][0][0];
                }
                return $return_msg;
            }
        } catch (\Exception $e) {
            throw new SystemException($e->getMessage());
        } finally {
            //删除临时文件
            FileUtil::deleteFileByAbsolutePath($filePath);
        }
        return null;
    }

    /**
     * 调用工作通接口
     * @param array $data 接口参数
     * @return array 接口返回数据
     */
    private function getIdouziApi($data = null)
    {
        if (!$data) {
            return null;
        }
        $url = Yii::$app->params['jobchatApiUrl'] . '?';
        try {
            $curl = new Curl();
            $curl->get($url . http_build_query($data));
            $curl->close();
            if ($curl->error) {
                return null;
            }
            $resp = base64_decode($curl->response);
            $resp = json_decode($resp, true);
        } catch (\Exception $e) {
            throw new SystemException($e->getMessage());
        }
        return $resp;
    }

    /**
     * 获取工作通cos签名
     * @param string $type 签名类型 file img
     * @return array|null  cos签名和上传地址
     */
    private function getCosConfig($type = 'file')
    {
        $resp = $this->getIdouziApi([
            'act' => 47,
            'cos_img' => $type == 'file' ? '' : 'on'
        ]);
        if ($resp['is_ok'] == 1) {
            $pcUrl = $type == 'file' ? 'cos_files_pc_url' : 'cos_images_pc_url';
            return [
                'sign' => $resp['data']['serv_data']['cos_sign'],
                'url' => $resp['data']['serv_data'][$pcUrl]
            ];
        }
        return null;
    }

    /**
     * cos2.0 上传
     * @param array $rq cos上传参数
     * @return
     */
    private function cosSend($rq)
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $rq['url']);
        switch (true) {
            case isset($rq['method']) && in_array(strtolower($rq['method']), array('get', 'post', 'put', 'delete', 'head')):
                $method = strtoupper($rq['method']);
                break;
            case isset($rq['data']):
                $method = 'POST';
                break;
            default:
                $method = 'GET';
        }
        $header = isset($rq['header']) ? $rq['header'] : array();
        $header[] = 'Method:' . $method;
        $header[] = 'User-Agent:QcloudPHP/2.0.1 (' . php_uname() . ')';
        isset($rq['host']) && $header[] = 'Host:' . $rq['host'];
        curl_setopt($ci, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method);
        isset($rq['timeout']) && curl_setopt($ci, CURLOPT_TIMEOUT, $rq['timeout']);
        isset($rq['data']) && in_array($method, array('POST', 'PUT')) && curl_setopt($ci, CURLOPT_POSTFIELDS, $rq['data']);
        $ssl = substr($rq['url'], 0, 8) == "https://" ? true : false;
        if (isset($rq['cert'])) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ci, CURLOPT_CAINFO, $rq['cert']);
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);
            if (isset($rq['ssl_version'])) {
                curl_setopt($ci, CURLOPT_SSLVERSION, $rq['ssl_version']);
            } else {
                curl_setopt($ci, CURLOPT_SSLVERSION, 4);
            }
        } else if ($ssl) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);   //true any ca
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);       //check only host
            if (isset($rq['ssl_version'])) {
                curl_setopt($ci, CURLOPT_SSLVERSION, $rq['ssl_version']);
            } else {
                curl_setopt($ci, CURLOPT_SSLVERSION, 4);
            }
        }
        $ret = curl_exec($ci);
        curl_close($ci);
        return $ret;
    }

    /****************工作通-idouzi客服回复消息 end*******************/
}
