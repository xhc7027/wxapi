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
use app\services\AppChooseServices;
use app\services\DataService;
use app\services\WeiXinService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;

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
                'actions' => ['bind-page', 'openid', 'web-page', 'app-info-clear-quota', 'get-accesstoken', 'get-supplier-nick-name']
            ],
            'apiAccess' => [
                'class' => 'app\behaviors\ApiAccessFilter',
                'actions' => ['material-add-material'],
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
        $accessToken = Yii::$app->weiXinService->getWebToken($appId);
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
}
