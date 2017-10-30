<?php

namespace app\commons;

use yii\base\InvalidParamException;

/**
 * 安全认证
 * Class SecurityUtil
 * @package app\services
 */
class SecurityUtil
{
    /**
     * @var array 待签名数组
     */
    private $post_arr;
    /**
     * @var 各服务签名key
     */
    private $signKey;

    /**
     * SecurityUtil constructor.
     * @param $post_arr
     * @param $signKey
     */
    public function __construct($post_arr, $signKey)
    {
        //判断代签名数据是否为数组
        if (!is_array($post_arr)) {
            throw new InvalidParamException('代签名数据不合法');
        }
        //判断认证key是否为空
        if (empty($signKey)) {
            throw new InvalidParamException('代签名Key为空');
        }
        //判断是否有带时间戳
        if (!isset($post_arr['timestamp']) && empty($post_arr['timestamp'])) {
            throw new InvalidParamException('签名缺少时间戳');
        }
        //判断时间是否过期
        if ($post_arr['timestamp'] + 600 < time()) {
            throw new InvalidParamException('签名过期');
        }
        $this->post_arr = $post_arr;
        $this->signKey = $signKey;
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
        $post_arr = $this->post_arr;
        $stringA = '';
        //1:将所有请求参数按照ASCII码从小到大排序（字典序）
        ksort($post_arr);
        foreach ($post_arr as $key => $value) {
            if ($key == 'sign') {
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
     * 签名认证
     * @return bool
     */
    public function signVerification()
    {
        $post_arr = $this->post_arr;
        //先判断sign是否存在
        if (!isset($post_arr['sign']) && empty($post_arr['sign'])) {
            throw new InvalidParamException('签名认证失败，缺少认证签名sign');
        }
        //获取新的签名
        $new_sign = $this->generateSign($this->post_arr, $this->signKey);
        //判断新老签名是否一致
        if ($post_arr['sign'] != $new_sign) {
            throw new InvalidParamException('签名认证失败，签名不匹配');
        }

        return true;
    }
}