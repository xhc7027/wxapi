<?php

namespace Idouzi\Commons;

use Yii;

/**
 * 页面缓存工具类
 *
 * <code>
 * 'lastModified' => function () {
 *   return HttpCacheUtil::getLastModified();
 * }
 * </code>
 *
 * @package Idouzi\Commons
 */
class HttpCacheUtil
{
    const LAST_MODIFIED = 'httpCacheLastModified_';

    /**
     * 动态生成当前访问Action对应的键
     *
     * @param string|null $key 客户端可以传入自定义键
     * @return string 返回动态生成的键
     */
    private static function buildKey(string $key = null): string
    {
        if (!$key) {
            //构造当前请求地址，不包含参数部分
            $key = Yii::$app->controller->module->id
                . '/' . Yii::$app->controller->id
                . '/' . Yii::$app->controller->action->id;
        }
        return self::LAST_MODIFIED . $key;
    }

    /**
     * 如果没有获取到页面最后一次修改时间，则填充当前时间以保证获取到最新内容。
     *
     * @param string|null $key 允许调用者传入指定键，这在获取不到当前请求地址时特别有用。
     * @return int 返回页面最后一次修改时间戳
     */
    public static function getLastModified(string $key = null): int
    {
        return Yii::$app->cache->exists(self::buildKey($key))
            ? intval(Yii::$app->cache->get(self::buildKey($key)))
            : time();
    }

    /**
     * 如果直接计算网页内容则有一定耗时，不如依赖于网页最近修复时间。
     *
     * @return string 返回页面指纹
     */
    public static function getETag(): string
    {
        return serialize(self::getLastModified());
    }

    /**
     * 更新某个具体Action的修改时间，通过这个方法可以更新缓存。
     *
     * @param string|null $key 键
     * @param int $timestamp 设置时间，默认当前时间就可以
     * @return bool 设置修改时间成功时返回true
     */
    public static function modified(string $key = null, int $timestamp = -1): bool
    {
        if (-1 === $timestamp) {
            $timestamp = time();
        }

        return Yii::$app->cache->set(self::buildKey($key), $timestamp);
    }
}