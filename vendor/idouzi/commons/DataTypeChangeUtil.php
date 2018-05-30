<?php

namespace Idouzi\Commons;


/**
 * 用于数据类型的转换
 *
 * @package Idouzi\Commons
 */
class DataTypeChangeUtil
{
    /**
     * 只用于将mongodb的_id转为字符，传进来的数组必须包含_id。会改变原数组的数据
     *
     * @param array $objectIdArr mongodb主键_id数组
     * @return array
     */
    public static function objectIdToString(array $objectIdArr)
    {
        foreach ($objectIdArr as $key => $objectId) {
            $objectIdArr[$key]['_id'] = $objectId['_id']->__toString();
        }
        return $objectIdArr;
    }

}