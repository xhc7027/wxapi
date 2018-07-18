<?php

namespace Idouzi\Commons;

/**
 * 公共工具方法
 *
 * @package app\services\utils
 */
class StringUtil
{
    /**
     * 随机生成指定位(默认16位)的字符串
     *
     * @param int $length 要生成字符的个数，默认16个
     * @return string 生成的字符串
     */
    public static function genRandomStr($length = 16)
    {
        $str = '';
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * 手机号的判断条件 兼容到香港乃至全球地区
     *
     * @param $num
     * @return bool
     */
    public static function isPhoneNumGlobal($num)
    {
        $match = "/(^1[3,5,7,8][0-9]{9}$)|(^[1-9]{1}[0-9]{7,9}$)/";
        if (preg_match($match, $num)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 从一段文本中获取图片链接地址
     *
     * @param string $str 文本
     * @return array 包含链接地址的数组
     */
    public static function getImageUrl(string $str): array
    {
        if (!$str) {
            return [];
        }

        $ret = null;

        $patterns = ['/<img.*?src="(http[s]{0,1}:\/\/.*?)"/', '/url\([\",\'](http[s]{0,1}:\/\/.*?)[\",\']\)/'];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $str, $matches);
            foreach ($matches[1] as $match) {
                $ret[] = $match;
            }
        }

        return $ret ? $ret : [];
    }

    /**
     * <p>从文件链接中拆解出文件后缀名</p>
     * 如果文件路径没有后缀名，则返回默认的文件后缀
     *
     * @param string $url 本地文件路径或远程文件路径
     * @return string
     */
    public static function getSuffixNameByType(string $url): string
    {
        $index = strpos($url, '.', -6);
        if (false === $index) {
            return 'jpeg';
        }
        return substr($url, $index + 1);
    }

    /**
     * 保n两位小数
     *
     * @param $n int 保留的小数位
     * @param $num string 数字
     * @return string
     */
    public static function keepDecimal($n, $num)
    {
        $n++;
        return substr(sprintf("%." . $n . "f", $num), 0, -1);
    }

    /**
     * 获取事务id
     *
     * @return string
     */
    public static function getTsId()
    {
        return 'tsId' . md5('tsId@' . time() . '@tsId@' . self::genRandomStr(8));
    }

    /*
     * 对手机号做脱敏处理
     * @param string $phone
     * @param int $start
     * @param int $length
     * @return mixed
     */
    public static function maskPhone($phone, $start = 3, $length = 4)
    {
        return substr_replace($phone, "****", $start, $length);
    }
}