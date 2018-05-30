<?php

namespace Idouzi\Commons;

use Yii;

/**
 * 修改页面浏览器缓存
 * @package Idouzi\Commons
 */
class ModifyHttpCacheUtil
{
    /**
     * 修改首页的缓存
     * @param string $keyName
     */
    public static function modifyWx(string $keyName)
    {
        $id = Yii::$app->params['system']['id']['wx'];
        Yii::$app->cache->keyPrefix = Yii::$app->params[$id]['cache']['keyPrefix'];
        HttpCacheUtil::modified($keyName);
        Yii::$app->cache->keyPrefix = Yii::$app->params[Yii::$app->id]['cache']['keyPrefix'];
    }

    /**
     * 修改首页的缓存
     * @param string $keyName
     */
    public static function modifyItem(string $keyName)
    {
        $id = Yii::$app->params['system']['id']['item'];
        Yii::$app->cache->keyPrefix = Yii::$app->params[$id]['cache']['keyPrefix'];
        HttpCacheUtil::modified($keyName);
        Yii::$app->cache->keyPrefix = Yii::$app->params[Yii::$app->id]['cache']['keyPrefix'];
    }
}