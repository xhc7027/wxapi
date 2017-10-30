<?php

namespace app\services;


use app\models\AppInfo;
use app\models\AppShareConf;
use yii\base\Exception;
use Yii;

class AppChooseServices
{
    /**
     * @var bool $supplierIdApp 是否是商家自己的公众号，true为是，false为不是
     */
    private $supplierIdApp = true;

    /**
     * @var string $functionName 需要调用的功能名称
     */
    private $functionName;

    /**
     * @var AppInfo|AppShareConf $appInfo 若为代分享账号，则是AppShareConf模型，反之是AppInfo模型
     */
    private $appInfo;

    /**
     * 在初始化的时候选定提供服务的公众号
     * @param string $queryId 选择的id
     * @param string $type 类型，可以是wxId或者appId
     * @param string $functionName 获取的功能名称
     * @throws Exception
     */
    public function __construct($queryId, $functionName, $type = 'appId')
    {
        if (!in_array($type, array('appId', 'wxId'))) {
            throw new Exception('类型错误');
        }
        $this->functionName = $functionName;
        //如果此值为空，则选择代分享的账号
        if (!$queryId) {
            $this->appInfo = (new AppShareConf())->getShareInfo();
            $this->supplierIdApp = false;
        } else {
            $this->appInfo = AppInfo::findOne([$type => $queryId, 'infoType' => 'authorized']);
            $this->confirmApp();
        }
    }

    /**
     * 判断公众号类型
     * @return string
     */
    private function getAppType()
    {
        //1. 是商家自己的公众号才需要判断，代分享都是认证服务号
        if (!$this->supplierIdApp) {
            return 'serviceAuth';
        }
        //2. 如果是服务号
        if ($this->appInfo->serviceTypeInfo === 2) {
            //2.1 微信认证服务号
            if ($this->appInfo->verifyTypeInfo === 0) {
                return 'serviceAuth';
            }
            //2.2 不是微信的认证服务号
            return 'service';
        }
        //2.3 微信认证订阅号
        if ($this->appInfo->verifyTypeInfo === 0) {
            return 'subscribeAuth';
        }
        //2.2 不是微信的认证订阅号
        return 'subscribe';
    }

    /**
     * 选择是否需要使用代分享
     */
    private function confirmApp()
    {
        //1. 如果没有选择功能，则什么都不做
        if (!$this->functionName) {
            return;
        }
        //2. 没有找到公众号信息，用代分享
        if (!$this->appInfo) {
            $this->appInfo = (new AppShareConf())->getShareInfo();
            $this->supplierIdApp = false;
        }
        //3. 如果存在公众号，则根据是否符合所需功能维持公众号或者选择代分享
        if (!Yii::$app->params['wxApiAuthorize'][$this->functionName][$this->getAppType()]) {
            $this->appInfo = (new AppShareConf())->getShareInfo();
            $this->supplierIdApp = false;
        }
    }


    /**
     * 返回accessToken
     * @return string
     */
    public function getAppAccessToken()
    {
        $appModel = $this->appInfo;
        return $appModel->accessToken;
    }

    /**
     * 返回appId
     * @return string
     */
    public function getAppId()
    {
        $appModel = $this->appInfo;
        return $appModel->appId;
    }

    /**
     * 判断是否是商家的公众号
     * @return bool
     */
    public function getSupplierIdApp()
    {
        return $this->supplierIdApp;
    }

    /**
     * 如果是 代分享则获取jsTicket
     * @return int|mixed|null
     */
    public function getJsApiTicket()
    {
        if (!$this->supplierIdApp) {
            return $this->appInfo->getJsApiTicket();
        }
        return null;
    }
}