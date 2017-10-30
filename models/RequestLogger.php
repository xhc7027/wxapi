<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property integer $id
 * @property string $appId
 * @property integer $type
 * @property string $method
 * @property string $reqTime
 * @property string $reqTimeStr
 * @property string $srcIp
 * @property string $reqUri
 * @property string $queryStr
 * @property string $postStr
 * @property string $respStr
 * @property integer $timeConsume
 * @package app\models
 */
class RequestLogger extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => '编号',
            'appId' => '授权方AppId',
            'type' => '日志类型',
            'method' => '请求类型',
            'reqTime' => '请求时间',
            'reqTimeStr' => '精确请求时间',
            'srcIp' => '来源IP',
            'reqUri' => '访问页面',
            'queryStr' => '请求参数',
            'postStr' => '请求主体数据',
            'timeConsume' => '请求处理耗时（秒）',
            'respStr' => '响应数据',
        ];
    }
}
