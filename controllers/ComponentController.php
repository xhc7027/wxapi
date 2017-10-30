<?php
namespace app\controllers;

use app\behaviors\MonitorBehavior;
use app\commons\SecurityUtil;
use app\commons\StringUtil;
use app\commons\wx\WXBizMsgCrypt;
use app\models\AppInfo;
use app\models\RespMsg;
use app\services\SecurityService;
use app\services\WeiXinService;
use Curl\Curl;
use yii;
use yii\base\InvalidParamException;
use yii\web\Controller;

/**
 * 用来处理来自微信端推送的数据
 * @package app\controllers
 */
class ComponentController extends Controller
{
    /**
     * @var 定义统一返回给微信的状态字符
     */
    const SUCCESS = 'success';
    /**
     * @var 推送component_verify_ticket
     */
    const COMPONENT_VERIFY_TICKET = 'component_verify_ticket';

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
            'monitor' => [
                'class' => MonitorBehavior::className(),
                'actions' => ['component-login-redirect', 'redirect', 'redirect-for-fx'],
            ]
        ];
    }

    /**
     * 授权事件接收URL<br>
     * 在下面四种事件下会进行推送：<br>
     * <ol>
     * <li>微信每间隔10分钟推送验证票据给第三方平台</li>
     * <li>公众号对第三方平台进行授权</li>
     * <li>公众号对第三方平台取消授权</li>
     * <li>公众号对第三方平台更新授权</li>
     * </ol>
     */
    public function actionAuthorizeDispatch()
    {
        if ($decodeXMLObj = $this->msgHandle()) {
            //推送验证票据
            if (self::COMPONENT_VERIFY_TICKET == $decodeXMLObj->InfoType[0]) {
                Yii::$app->weiXinService->saveVerifyTicket($decodeXMLObj);
            } else {//授权事件
                Yii::$app->weiXinService->handleChangeAuthorization($decodeXMLObj);
            }
        }
        return self::SUCCESS;
    }

    /**
     * 接受来自微信推送的消息，包括普通消息和事件消息<br>
     *
     * @link  被动普通消息 https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140453&token=&lang=zh_CN
     * @return string 返回处理状态或回复消息
     */
    public function actionAppDispatch()
    {
        $result = self::SUCCESS;
        if ($decodeXMLStr = $this->msgHandle(false)) {
            try {
                //准备签名参数
                $params = ['appId' => Yii::$app->session->get(MonitorBehavior::APP_ID), 'timestamp' => time()];
                $sign = (new SecurityUtil($params, Yii::$app->params['signKey']['msgSignKey']))
                    ->generateSign();
                $params['sign'] = $sign;

                //进入具体的消息处理业务
                $curl = new Curl();
                $curl->post(Yii::$app->params['serviceDomain']['weiXinMsgDomain'] . '/facade/receive-wx-msg?'
                    . http_build_query($params), ['decodeXMLStr' => $decodeXMLStr]);
                if (!$curl->error) {
                    $response = json_decode($curl->response);
                    if (isset($response->return_code) && $response->return_code == RespMsg::SUCCESS) {
                        $result = $this->encode($response->return_msg);
                    } else {//如果对方处理失败则记录日志
                        Yii::error('接收消息:' . $decodeXMLStr . ',下发消息系统返回错误:' . $response->return_msg, __METHOD__);
                    }
                } else {
                    Yii::error('下发请求到消息处理系统出错:' . $curl->error, __METHOD__);
                }
            } catch (InvalidParamException $e) {
                Yii::error($e->getMessage(), __METHOD__);
            }
        }

        return $result;
    }

    /**
     * 消息加密后返回给微信
     * @param string $respMsg XML格式的消息内容
     * @return string 返回加密后的XML字符串
     */
    private function encode($respMsg)
    {
        $msg = '';
        $token = Yii::$app->params['wxConfig']['token'];
        $encodingAesKey = Yii::$app->params['wxConfig']['encodingAESKey'];
        $appId = Yii::$app->params['wxConfig']['appId'];

        $pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);

        $timestamp = time();
        $nonceStr = StringUtil::getRandomStr();
        $pc->encryptMsg($respMsg, $timestamp, $nonceStr, $msg);
        return $msg;
    }

    /**
     * 统一微信推送过来的消息处理<br>
     *
     * 收到消息的后处理策略：<br>
     * <ol>
     * <li>对请求参数签名，保持来源安全</li>
     * <li>获取请求体数据</li>
     * <li>消息解密</li>
     * <li>记录请求日志</li>
     * <li>返回给业务处理对象</li>
     * </ol>
     *
     * 访问解析出来的XML对象中的元素：<br>
     * 例如接收到的消息如下，
     * <code>
     * <xml>
     *   <ToUserName><![CDATA[toUser]]></ToUserName>
     *   <FromUserName><![CDATA[fromUser]]></FromUserName>
     *   <CreateTime>1348831860</CreateTime>
     *   <MsgType><![CDATA[text]]></MsgType>
     *   <Content><![CDATA[this is a test]]></Content>
     *   <MsgId>1234567890123456</MsgId>
     * </xml>
     * </code>
     * 当你想获取MsgType元素的值，那么你可以像这样来操作：<br>
     * <code>
     * $decodeXMLObj->MsgType[0];
     * </code>
     * @param bool $flag 是否需要转换成对象
     * @return null|\SimpleXMLElement
     */
    private function msgHandle($flag = true)
    {
        //从会话中取出解密后的XML字符串
        $decodeXMLStr = Yii::$app->session->get(MonitorBehavior::DECODE_XML_STR);
        Yii::$app->session->remove(MonitorBehavior::DECODE_XML_STR);
        if ($flag) {
            return simplexml_load_string($decodeXMLStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        return $decodeXMLStr;
    }

    /**
     * 代公众号网页授权后从微信那边回调的地址
     * @param string $state 服务商上一次调用接口时自定义传入的参数
     * @param null|string $code
     * @param null|string $appid
     * @return yii\web\Response
     */
    public function actionRedirect($state, $code = null, $appid = null)
    {
        if ($code && $appid) {
            $respMsg = Yii::$app->weiXinService->getWebPageAccessToken($appid, $code);
            if ($respMsg->return_code == RespMsg::FAIL) {
                $state = urldecode($state) . '&' . json_encode($respMsg->return_msg);
            } else {
                $openid = isset($respMsg->return_msg->openid) ? $respMsg->return_msg->openid : null;
                $access_token = isset($respMsg->return_msg->access_token) ? $respMsg->return_msg->access_token : null;
                $refresh_token = isset($respMsg->return_msg->refresh_token) ? $respMsg->return_msg->refresh_token : null;
                $state = urldecode($state) . '&openid=' . $openid . '&access_token=' . $access_token
                    . '&refresh_token=' . $refresh_token;
            }
        }

        //跳转到之前用户访问的地址
        Yii::trace('跳转到之前用户访问的地址' . $state, __METHOD__);
        return $this->redirect($state);
    }

    /**
     * 代公众号网页授权后从微信那边回调的地址(只针对微分销)
     * @param string $state 服务商上一次调用接口时自定义传入的参数
     * @param null|string $code
     * @param null|string $appid
     * @return yii\web\Response
     */
    public function actionRedirectForFx($state, $code = null, $appid = null)
    {
        $state = Yii::$app->weiXinService->getShorturl($state) . '&code=' . $code;
        //跳转到之前用户访问的地址
        Yii::warning('跳转到之前用户访问的地址' . $state, __METHOD__);
        return $this->redirect($state);
    }

    /**
     * 保存微信跳转过来的数据<br>
     * 使用授权码换取公众号的接口调用凭据和授权信息
     * @param $auth_code
     * @param int $expires_in
     * @param int $wxid
     * @return string
     */
    public function actionComponentLoginRedirect($auth_code, $expires_in, $wxid)
    {
        $appInfo = new AppInfo();
        $appInfo->authorizationCode = $auth_code;
        $appInfo->infoType = WeiXinService::UPDATEAUTHORIZED;
        $appInfo->authorizationCodeExpiredTime = $expires_in + time();
        $appInfo->twoUpdatedAt = time();
        $respMsg = Yii::$app->weiXinService->getComponentAccessToken();
        $componentAccessToken = $respMsg->return_msg['accessToken'];
        $respMsg = $appInfo->getQueryAuth($componentAccessToken);

        if ($respMsg->return_code == RespMsg::FAIL) {
            Yii::error('获取公众号接口调用凭据和授权信息出错', __METHOD__);
            return $respMsg->toJsonStr();
        }

        //保存通过接口请求到的授权信息
        $result = $respMsg->return_msg;
        $authorizationInfo = $result->authorization_info;
        $model = AppInfo::findOne(strval($authorizationInfo->authorizer_appid));
        if (!$model) {
            $model = new AppInfo();
            $model->appId = $authorizationInfo->authorizer_appid;
        }
        $model->accessToken = $authorizationInfo->authorizer_access_token;
        $model->refreshToken = $authorizationInfo->authorizer_refresh_token;
        $funcInfoAry = $authorizationInfo->func_info;
        foreach ($funcInfoAry as $category) {
            $tmpAry[] = $category->funcscope_category->id;
        }
        $model->funcScopeCategory = json_encode($tmpAry);
        if (isset($authorizationInfo->expiresIn)) {//在授权的公众号具备API权限时，才有此返回值
            $model->expiresIn = $authorizationInfo->expiresIn;
        }
        $model->zeroUpdatedAt = time();
        $model->authorizationCode = $appInfo->authorizationCode;
        $model->infoType = $appInfo->infoType;
        $model->authorizationCodeExpiredTime = $appInfo->authorizationCodeExpiredTime;
        $model->twoUpdatedAt = $appInfo->twoUpdatedAt;
        if ($this->isValidWxid($wxid, $model)) {
            $model->wxId = $wxid;
        }
        $model->save();

        //获取授权方的公众号帐号基本信息
        $model->getAuthorizeInfo($componentAccessToken);

        //通知回爱豆子
        $params = ['r' => 'supplier/index/index', 'appId' => $model->appId, 'timestamp' => time()];
        try {
            $sign = (new SecurityUtil($params, Yii::$app->params['signKey']['iDouZiSignKey']))->generateSign();
        } catch (InvalidParamException $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return (new RespMsg(['return_code' => RespMsg::FAIL]))->toJsonStr();
        }

        $this->redirect(Yii::$app->params['serviceDomain']['iDouZiDomain']
            . '/supplier/index/index?' . http_build_query($params) . '&sign=' . $sign);
    }

    /**
     * 判断此次绑定是否合法
     * @param $wxId
     * @param $appInfo
     * @return bool
     */
    private function isValidWxid($wxId, $appInfo)
    {
        //1：先看这个appId是否已经绑定了其他wxid
        if ($appInfo->wxId && $appInfo->wxId != $wxId) {
            Yii::trace('[该公众号' . $appInfo->appId . '已经绑定其他账号，wxid=' . $wxId . ']', __METHOD__);
            return false;
        }
        //2: 再判断wxId，是否绑定在其他公众号上。是的话将其置为null
        $model = AppInfo::findOne(['wxId' => $wxId]);
        if ($model && $model->appId != $appInfo->appId) {
            Yii::trace('[该账号wxid=' . $wxId . '之前绑定的公众号id=' . $model->appId . ']', __METHOD__);
            $model->wxId = null;
            return $model->save();
        } else {
            return true;
        }
    }

}
