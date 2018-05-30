<?php

namespace Idouzi\Commons;

use Idouzi\Commons\Exceptions\SystemException;
use Yii;

/**
 * JSON Web Token（JWT）是一种开放式标准（RFC 7519），它定义了一种紧凑且自包含的方式，用于在各方之间以JSON对象安全传输信息。这些信息可以通过数
 * 字签名进行验证和信任。可以使用秘密（使用HMAC算法）或使用RSA的公钥/私钥对对JWT进行签名。<br>
 *
 * 用途说明：在写数据到Cookie时可以用本工具进行处理，之后再将返回的结果写进Cookie。
 * 下次从Cookie中读取到数据后，可以用本工具进行解析，如此可以保证信息不会被伪造，即“你看到的肯定你是写的”。
 *
 * @package Idouzi\Commons
 */
class JWTUtil
{
    /**
     * @var array 标题通常由两部分组成：令牌的类型，即JWT和正在使用的散列算法，如HMAC SHA256或RSA。
     */
    private $header;
    /**
     * @var array
     */
    private $payload;
    /**
     * @var string 签名私钥
     */
    private $key;
    /**
     * @var string 最后要返回的结果
     */
    private $ret;

    /**
     * JWTUtil constructor.
     * @param string|null $key 参与加密的公钥
     * @param int $exp 数据过期时间，仅仅作为一个标识不影响Cookie，默认1天以后
     */
    public function __construct(string $key = null, int $exp = 0)
    {
        $this->payload = ['exp' => $exp];
        $this->header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $this->key = $key ? $key : Yii::$app->params['publicKeys'][Yii::$app->id];
    }

    /**
     * 对数据预见处理
     */
    private function pretreatment()
    {
        $this->ret = base64_encode(json_encode($this->header)) . '.' . base64_encode(json_encode($this->payload));
    }

    /**
     * 对数据加密
     * @param string|array $data 要处理的数据
     * @return string 返回处理过后的字符串
     */
    public function encode($data): string
    {
        $this->payload['data'] = $data;
        $this->pretreatment();
        $text = $this->ret . $this->key;
        return $this->ret .= '.' . $this->crypt($text);
    }

    /**
     * 使用 HMAC 方法生成带有密钥的哈希值
     *
     * @param string $data
     * @return string
     */
    private function crypt(string $data)
    {
        return hash_hmac("sha256", $data, $this->key, false);
    }

    /**
     * 解密数据
     * @param string $data 要还原内容的字符串
     * @return array|mixed
     * @throws SystemException
     */
    public function decode(string $data)
    {
        //根据.分隔三段文本
        $parser = preg_split('/\./', $data);
        if (!$parser || count($parser) !== 3) {
            throw new SystemException('原始数据格式错误');
        }

        //比对签名
        var_dump($this->crypt($parser[0] . '.' . $parser[1] . $this->key));
        if ($parser[2] !== $this->crypt($parser[0] . '.' . $parser[1] . $this->key)) {
            throw new SystemException('签名错误');
        }

        //解析数据
        $this->header = json_decode(base64_decode($parser[0]));
        $this->payload = json_decode(base64_decode($parser[1]));
        return $this->payload;
    }

}