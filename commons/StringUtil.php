<?php

namespace app\commons;

class StringUtil
{
    /**
     * 随机生成指定位（默认16位）字符串
     * @param int $length 要生成字符的个数，默认16个
     * @return string 生成的字符串
     */
    public static function getRandomStr($length = 16)
    {
        $str = '';
        $str_pol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * 将中文字符数据包打包成二进制串
     * @param string $str
     * @return mixed
     */
    public static function packForJsonStr($str)
    {
        return preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            create_function('$matches', 'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'),
            $str);
    }
}
