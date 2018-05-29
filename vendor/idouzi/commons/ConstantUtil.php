<?php

namespace Idouzi\Commons;

use Yii;

/**
 * 常量的集中定义，从配置文件中获取常量值。
 *
 * @package Idouzi\Commons
 */
class ConstantUtil
{
    /**
     * 从配置文件中获取指定常量值。
     *
     * @param string $key 常量的名称，不包含“constant.cookies.”
     * @return string
     */
    public static function getCookieName(string $key): string
    {
        return Yii::$app->params['constant']['cookies'][$key] ?? '';
    }

    /**
     * 从配置文件中获取指定常量值。
     *
     * @param string $key 常量的名称，不包含“constant.sessions.”
     * @return string
     */
    public static function getSessionName(string $key): string
    {
        return Yii::$app->params['constant']['sessions'][$key] ?? '';
    }

    /**
     * 从配置文件中获取指定常量值。
     *
     * @param string $key 常量的名称，不包含“constant.caches.”
     * @return string
     */
    public static function getCacheName(string $key): string
    {
        return Yii::$app->params['constant']['caches'][$key] ?? '';
    }
}