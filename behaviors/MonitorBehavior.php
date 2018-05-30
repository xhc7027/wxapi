<?php

namespace app\behaviors;

use app\commons\DateTimeUtil;
use app\commons\wx\WXBizMsgCrypt;
use app\models\RequestLogger;
use yii;
use yii\base\Behavior;
use yii\web\Controller;

/**
 * 监控指定请求并保存其数据
 * @package app\behaviors
 */
class MonitorBehavior extends Behavior
{
    /**
     * @var string 从微信解密出来的XML字符串
     */
    const DECODE_XML_STR = 'WEI_XIN_API_DECODE_XML_STR';

    /**
     * @var string 公众号AppId
     */
    const APP_ID = 'WEI_XIN_API_APP_ID';

    /**
     * @var float 记录请求进来的时间
     */
    private $timeStart = null;

    /**
     * @var RequestLogger
     */
    private $reqLogModel = null;

    /**
     * @var array 仅过滤此数组中声明的action
     */
    public $actions = [];

    /**
     * @return array
     */
    public function events()
    {
        return [
            Controller::EVENT_BEFORE_ACTION => 'beforeAction',
            Controller::EVENT_AFTER_ACTION => 'afterAction',
        ];
    }

    /**
     * @param \yii\base\ActionEvent $event
     * @return boolean
     */
    public function beforeAction($event)
    {
        $controllId = $event->action->controller->id;
        $actionId = $event->action->id;

        $this->timeStart = DateTimeUtil::microTimeFloat();

        $this->reqLogModel = new RequestLogger();
        $this->reqLogModel->method = $_SERVER['REQUEST_METHOD'];
        $this->reqLogModel->reqTime = date("Y-m-d H:i:s", $_SERVER['REQUEST_TIME']);
        $this->reqLogModel->reqTimeStr = DateTimeUtil::microTimeFormat($_SERVER['REQUEST_TIME_FLOAT']);
        $this->reqLogModel->srcIp = $_SERVER['REMOTE_ADDR'];
        $index = strpos($_SERVER['REQUEST_URI'], '?');
        if ($index === false) {
            $index = strpos($_SERVER['REQUEST_URI'], '&');
            if ($index === false) {
                $index = strlen($_SERVER['REQUEST_URI']);
            }
        }
        $this->reqLogModel->reqUri = substr($_SERVER['REQUEST_URI'], 0, $index);
        $this->reqLogModel->queryStr = $_SERVER['QUERY_STRING'];
        $appid = null;
        if ('component' == $controllId && !in_array($actionId, $this->actions)) {//微信回调用的控制器
            //获取请求中的公众号ID
            $decodeXMLStr = $this->msgHandle();//获取解密后的XML字符
            $appid = Yii::$app->request->get('appid');
            if ($appid && strpos($appid, '/') === 0) {//将appid前面的斜线处理
                $appid = substr($appid, 1, strlen($appid));
            } else {
                $decodeXMLObj = simplexml_load_string($decodeXMLStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                if (isset($decodeXMLObj->AuthorizerAppid)) {
                    $appid = $decodeXMLObj->AuthorizerAppid;
                }
            }
            Yii::$app->session->set(self::APP_ID, $appid);

            $this->reqLogModel->type = 1;
            $this->reqLogModel->postStr = $decodeXMLStr;
            //将解密字符传递给当前Action处理
            Yii::$app->session->set(self::DECODE_XML_STR, $decodeXMLStr);
        } else if ('facade' == $controllId) {//内部业务系统用的控制器
            $this->reqLogModel->type = 0;
            $this->reqLogModel->postStr = json_encode($_POST);
            $appid = Yii::$app->request->get('appId');
        } else {
            $this->reqLogModel->type = 1;
            $this->reqLogModel->postStr = json_encode($_POST);
        }
        $this->reqLogModel->appId = $appid;

        return $event->isValid;
    }

    /**
     * @param \yii\base\ActionEvent $event
     * @return boolean
     */
    public function afterAction($event)
    {
        $actionId = $event->action->id;

        if ($event->result instanceof yii\base\BaseObject) {
            $this->reqLogModel->respStr = json_encode($event->result);
        } else {
            $this->reqLogModel->respStr = $event->result;
        }
        $timeEnd = DateTimeUtil::microTimeFloat();
        $time = $timeEnd - $this->timeStart;
        $this->reqLogModel->timeConsume = $time;
        $this->reqLogModel->insert();

        return $event->isValid;
    }

    /**
     * 针对微信推送过来的消息做预处理<br>
     * 主要包括参数的验签以及消息解密。
     * @return null|string
     */
    private function msgHandle()
    {
        $decodeXMLStr = null;
        if ($this->checkSignature()) {
            $xmlStr = file_get_contents('php://input');
            Yii::trace('收到来自微信推送消息：' . $xmlStr, __METHOD__);
            //将消息解析成数据对象
            $postXMLObj = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($postXMLObj) {//消息解密
                $decodeXMLStr = $this->decode($postXMLObj);
            }
        } else {
            Yii::warning('验证签名错误', __METHOD__);
        }
        return $decodeXMLStr;
    }

    /**
     * 验证消息签名
     * @return bool 正确返回true，否则返回false
     */
    private function checkSignature()
    {
        $token = Yii::$app->params['wxConfig']['token'];

        $signature = Yii::$app->request->get('signature');
        $timestamp = Yii::$app->request->get('timestamp');
        $nonce = Yii::$app->request->get('nonce');

        $tmpArr = [$token, $timestamp, $nonce];

        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        }
        return false;
    }

    /**
     * 解密微信传过来的消息
     * @param $postXMLObj
     * @return string
     */
    private function decode($postXMLObj)
    {
        $msgSignature = Yii::$app->request->get('msg_signature');//消息体签名，用于验证消息体的正确性
        $timeStamp = Yii::$app->request->get('timestamp');
        $nonce = Yii::$app->request->get('nonce');

        // 第三方收到公众号平台发送的消息
        $msg = '';
        $token = Yii::$app->params['wxConfig']['token'];
        $encodingAesKey = Yii::$app->params['wxConfig']['encodingAESKey'];
        $appId = Yii::$app->params['wxConfig']['appId'];

        $pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);
        $errCode = $pc->decryptMsg($msgSignature, $timeStamp, $nonce, $postXMLObj, $msg);

        Yii::trace('errCode:' . $errCode . ', msg:' . $msg, __METHOD__);
        if ($errCode != 0) {
            Yii::error('消息:' . $postXMLObj->asXML() . ', 解密错误:' . $errCode, __METHOD__);
        }

        return $msg;
    }
}
