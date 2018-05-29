<?php

namespace Idouzi\Commons;

/**
 * aes加密工具类
 *
 * @package app\commons
 */
class AesCodeUtil
{
    //密钥
    private $_secret_key;

    //偏移量 iv
    private $_iv;

    /**
     * 构造的时候给key和iv赋值
     *
     * @param $aesCodeKey string $_secret_key的值
     * @param $aesIvKey string $_iv的值
     */
    public function __construct($aesCodeKey, $aesIvKey)
    {
        $this->_secret_key = base64_encode($aesCodeKey);
        $this->_iv = $aesIvKey;
    }

    /**
     * 加密方法
     * @param string $str
     * @return string
     */
    public function encrypt($str)
    {
        //AES, 128 ECB模式加密数据
        $encrypt_str = openssl_encrypt(
            $str,
            'aes-128-cbc',
            base64_decode($this->_secret_key),
            OPENSSL_RAW_DATA,
            $this->_iv
        );
        return base64_encode($encrypt_str);
    }

    /**
     * 解密方法
     * @param string $str
     * @return string
     */
    public function decrypt($str)
    {
        //AES, 128 ECB模式解密数据
        $encrypted = base64_decode($str);
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-128-cbc',
            base64_decode($this->_secret_key),
            OPENSSL_RAW_DATA,
            $this->_iv
        );
        return $decrypted;
    }

}