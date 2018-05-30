<?php

namespace Idouzi\Commons;

use Yii;
use yii\base\InvalidArgumentException;

/**
 * HTTP接口调用安全认证工具类，包含生成签名和校验签名的公共方法。
 *
 * @package Idouzi\Commons
 */
class SecurityUtil
{
    /**
     * @var array 签名数组
     */
    private $data;
    /**
     * @var string 签名key
     */
    private $signKey;

    /**
     * 初始化工具类
     *
     * @param array $data 签名数组
     * @param string $signKey 签名key
     */
    public function __construct(array $data, string $signKey = null)
    {
        //如果没有传递签名公钥，则默认从公共配置文件读取
        if (!$signKey) {
            if (!$signKey = Yii::$app->params['publicKeys'][Yii::$app->id] ?? null) {
                throw new InvalidArgumentException('缺少签名公钥');
            }
        }
        $this->signKey = $signKey;

        //是否有带时间戳
        if (!isset($data['timestamp']) || !$data['timestamp']) {
            throw new InvalidArgumentException('缺少时间戳');
        }

        //时间是否过期，暂定10分钟内有效
        if (intval($data['timestamp']) + 600 <= time()) {
            throw new InvalidArgumentException('时间戳已过期');
        }

        $this->data = $data;
    }

    /**
     * 生成签名
     * <p>
     * 1.将所有请求参数按照ASCII码从小到大排序（字典序），使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串stringA；
     * 2.在stringA后面追加服务供应商预先提供的密钥（appSecret），此时stringB = stringA + appSecret
     * 3.签名于 md5(stringB);
     * 4.sign 字段不参与签名
     * </p>
     * @return string
     */
    public function generateSign()
    {
        $stringA = '';
        //1:将所有请求参数按照ASCII码从小到大排序（字典序）
        ksort($this->data);
        foreach ($this->data as $key => $value) {
            if ($key === 'sign') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $stringA .= $key . '=' . $value . '&';
        }
        //2：拼接参数
        $stringB = $stringA . $this->signKey;
        //3：生成签名
        $sign = md5($stringB);
        return $sign;
    }

    /**
     * 校验签名是否正确
     *
     * @return bool
     */
    public function signVerification()
    {
        //先判断sign是否存在
        if (!isset($this->data['sign']) || !$this->data['sign']) {
            throw new InvalidArgumentException('签名认证失败，缺少认证签名sign');
        }

        //获取新的签名
        $newSign = $this->generateSign();

        //判断新老签名是否一致
        if ($this->data['sign'] !== $newSign) {
            throw new InvalidArgumentException('签名认证失败，签名不匹配');
        }

        return true;
    }
}