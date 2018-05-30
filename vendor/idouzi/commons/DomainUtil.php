<?php

namespace Idouzi\Commons;

use Yii;

/**
 * 域名处理工具类
 *
 * Class DomainUtil
 * @package Idouzi\Commons
 */
class DomainUtil
{
    /**
     * 链接校验匹配的模式
     */
    private static $pattern = '/^{schemes}:\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i';

    /**
     * 认可的协议
     */
    private static $validSchemes = ['http', 'https'];

    /**
     * 正则匹配二级域名
     * @param $url
     * @return array|boolean
     */
    public static function getSLDMatches($url)
    {
        if (preg_match("/^(http:\/\/|https:\/\/)?([^\/:]+\.)?([^\/:]+\.[^\/:]+\..+)$/", $url, $matchs)) {
            return $matchs;
        } else {
            return false;
        }
    }

    /**
     * 获取对应域名的三级链接地址
     * @param  $host
     * @param  $wxid
     * @param  $url
     * @return boolean|string
     */
    public static function getTLD($host, $wxid, $url = "", $hostupdate = "")
    {
        $matchs = self::getSLDMatches($host);
        if (!empty($hostupdate)) {
            $matchs[3] = $hostupdate . "." . substr($matchs[3], (strpos($matchs[3], ".") + 1));
        }
        $redirct_url = $matchs[1] . $wxid . "." . $matchs[3];
        if ($host == $redirct_url) {
            return false;
        } else {
            return $redirct_url . $url;//需跳转地址
        }
    }

    /**
     * 检验是否是三级域名
     *
     * @return bool
     */
    public static function checkDomain()
    {
        $url = Yii::$app->request->getAbsoluteUrl();
        $url = self::getSLDMatches($url);
        return !empty($url[2]);
    }

    /**
     * 判断是否是链接
     * @param $url string 链接
     * @param null $defaultScheme 默认的模式
     * @return bool
     */
    public static function checkUrl($url, $defaultScheme = null)
    {
        // make sure the length is limited to avoid DOS attacks
        if (is_string($url) && strlen($url) < 2000) {
            if ($defaultScheme !== null && strpos($url, '://') === false) {
                $url = $defaultScheme . '://' . $url;
            }

            if (strpos(self::$pattern, '{schemes}') !== false) {
                $pattern = str_replace('{schemes}', '('
                    . implode('|', self::$validSchemes) . ')', self::$pattern);
            } else {
                $pattern = self::$pattern;
            }

            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}