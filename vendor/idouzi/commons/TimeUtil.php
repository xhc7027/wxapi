<?php


namespace Idouzi\Commons;

use Yii;

/**
 * 处理与时间有关的工具
 *
 * @package Idouzi\Commons
 */
class TimeUtil
{
    /**
     * 返回当前的毫秒数
     * @return float
     */
    public static function msecTime()
    {
        list($msecTime, $secTime) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($msecTime) + floatval($secTime)) * 1000);
    }

    /**
     * 获取 年-月-日 的时间戳
     *
     * @param null $time 需要获取的时间
     * @return false|int
     */
    public static function getYmdTimestamp($time = null)
    {
        return strtotime(self::getDateYmd($time));
    }

    /**
     * 获取对应时间的 年-月-日 日期格式
     *
     * @param int|string $time 时间戳或者字符日期
     * @return false|string
     */
    public static function getDateYmd($time = null)
    {
        return $time ? self::getDate($time, 'Y-m-d') : self::getDate(time(), 'Y-m-d');
    }

    /**
     * @param $time int|string 时间戳或者字符日期
     * @param $format string 对应的格式
     * @return false|string 返回需要的日期格式
     */
    private static function getDate($time, $format)
    {
        return is_int($time) ? date($format, $time) : date($format, strtotime($time));
    }

    /**
     * 符合请求时间的数据处理日期格式
     * @param  $numDay
     * @param string $value
     * @return string
     */
    public static function collatingDate($numDay, string $value)
    {
        return in_array($numDay, [Yii::$app->params['lookDay'][1], Yii::$app->params['lookDay'][2]])
            ? trim(substr($value, 10, 12))
            : trim(substr($value, 0, 10));
    }

    /**
     * 按传过来的天数计算对应开始时间和结束时间
     *
     * @param int $numDay 天数 0代表当天，1代表昨天
     * @return array
     */
    public static function timeCalculation(int $numDay): array
    {
        if (in_array($numDay, [Yii::$app->params['lookDay'][1],
            Yii::$app->params['lookDay'][2]])) {
            return [
                'sdate' => date('Y-m-d 00:00:00', strtotime(-$numDay . ' day')),
                'edate' => date('Y-m-d 23:59:59', strtotime(-$numDay . ' day'))
            ];
        }
        return [
            'edate' => date('Y-m-d h:i:s', strtotime('0 day')),
            'sdate' => date('Y-m-d h:i:s', strtotime(-$numDay . ' day'))
        ];
    }

    /**
     * 获取小时
     *
     * @param $date
     * @return bool|string
     */
    public static function getStringH($date)
    {
        return date('H', strtotime($date));
    }

    /**
     * 获取年月日
     *
     * @param $date
     * @return bool|string
     */
    public static function getStringYmd($date)
    {
        return date('Y-m-d', strtotime($date));
    }

    /**
     * 获取距离凌晨的秒数
     *
     * @return false|int
     */
    public static function getDistanceTomorrowTime()
    {
        return strtotime(date('Y-m-d', strtotime('+1 day'))) - time();
    }
}