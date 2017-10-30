<?php

namespace app\services;


use app\models\RequestLogHour;

/**
 * 日志业务处理
 *
 * Class RequestLogService
 * @package app\services
 */
class RequestLogService
{
    /**
     * 按小时插入日志统计
     *
     * @param $data
     *
     * @return bool
     */
    public static function insertRequestLogHour($data)
    {
        return (new RequestLogHour())->insertData($data);
    }

    public static function getRequstLogHourData($condition, $select = [])
    {
        if (!$select) {
            $select = ['reqHour AS reqTime', 'minTimeConsume', 'maxTimeConsume', 'number'];
        }
        return RequestLogHour::getRequestLogHourData($select, $condition);
    }
}