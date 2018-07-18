<?php

namespace Idouzi\Commons;


use Idouzi\Commons\Exceptions\HttpException;
use Idouzi\Commons\Exceptions\SystemException;
use Yii;

/**
 * 节点调用adfunds一系列接口
 */
class NodeApiUtil
{

    /**
     * 节点创建广告位
     *
     * @param string $name
     * @return mixed
     * @throws \Idouzi\Commons\Exceptions\SystemException
     */
    public static function createAdsense(string $name)
    {
        if (!$name) {
            throw new SystemException('参数不正确');
        }
        //请求参数
        $data = [
            'name' => $name,
            'platformId' => Yii::$app->params['adsense']['platformId'],
            'style' => [
                'backgroundColor' => Yii::$app->params['adsense']['style']['backgroundColor'],
                'color' => Yii::$app->params['adsense']['style']['color'],
                'fontSize' => Yii::$app->params['adsense']['style']['fontSize']
            ],
            'templateId' => Yii::$app->params['adsense']['templateId'],
            'uid' => Yii::$app->params['adsense']['uid'],
            'appId' => Yii::$app->params['adsense']['appId']
        ];

        return self::request(
            Yii::$app->params['domains']['adfunds'] . Yii::$app->params['nodeId']['createAdsense'],
            $data
        );
    }

    /**
     * 发送HTTP请求并判断响应数据是否异常，在请求成功的情况下返回业务数据。
     *
     * @param string $url 请求url
     * @param array  $data 请求数据
     * @return mixed
     * @throws \Idouzi\Commons\Exceptions\HttpException
     */
    public static function request(string $url, $data = [])
    {
        try {
            //签名
            $sign = self::generateSign($data, Yii::$app->params['nodeId']['signKey']);
            $data['sign'] = $sign;
            $response = HttpUtil::simplePost($url, $data);
        } catch (\Exception $e) {
            Yii::error('调用adfunds接口失败:' . $url . '失败:' . $e->getTraceAsString(), __METHOD__);
            throw new HttpException('接口请求异常:' . $e->getMessage());
        }

        $responseAry = json_decode($response, true);
        if (!is_array($responseAry)) {
            Yii::error('调用adfunds接口失败:' . $url . '失败:' . $response, __METHOD__);
            throw new HttpException("调用adfunds接口失败");
        }

        return $responseAry;
    }

    /**
     * 生成签名
     * <p>
     * 1.将所有请求参数按照ASCII码从小到大排序（字典序），使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串stringA；
     * 2.在stringA后面追加服务供应商预先提供的密钥（appSecret），此时stringB = stringA + appSecret
     * 3.签名于 md5(stringB);
     * 4.sign 字段不参与签名
     * </p>
     *
     * @return string
     */
    public static function generateSign($data, $signKey)
    {
        $stringA = '';
        //1:将所有请求参数按照ASCII码从小到大排序（字典序）
        ksort($data);
        foreach ($data as $key => $value) {
            if ($key === 'sign') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $stringA .= $key . '=' . $value . '&';
        }
        //2：拼接参数
        $stringB = $stringA . $signKey;
        //3：生成签名
        $sign = md5($stringB);
        return $sign;
    }

}