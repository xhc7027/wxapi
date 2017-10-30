<?php

namespace app\commons;

/**
 * 时间处理工具
 * @package app\commons
 */
class DateTimeUtil
{
    /**
     * 时间毫秒戳转换成时间字符串表现形式
     * @param $time
     * @param $tag
     * @return string
     */
    public static function microTimeFormat($time, $tag = 'Y-m-d H:i:s.x')
    {
        if (strpos($time, '.')) {
            list($usec, $sec) = explode(".", $time);
        } else {
            $usec = $time;
            $sec = 0;
        }
        $date = date($tag, $usec);
        return str_replace('x', $sec, $date);
    }

    /**
     * 获取毫秒时间戳
     * @return float
     */
    public static function microTimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
