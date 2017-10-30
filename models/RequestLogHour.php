<?php

namespace app\models;

use app\exceptions\ModelValidateException;
use yii\db\ActiveRecord;

/**
 * @property integer $id
 * @property integer $type
 * @property string  $reqDay
 * @property string  $reqHour
 * @property integer $minTimeConsume
 * @property integer $maxTimeConsume
 * @property integer $number
 * @property string  $createTime
 * @package app\models
 */
class RequestLogHour extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'request_log_hour';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => '编号',
            'type' => '日志类型',
            'reqDay' => '请求年月日',
            'reqHour' => '请求小时',
            'minTimeConsume' => '最小消耗时间',
            'maxTimeConsume' => '最大消耗时间',
            'number' => '每小时请求数据',
            'createTime' => '创建时间',
        ];
    }

    /**
     * 插入日志统计的数据
     *
     * @param array $data
     *
     * @return bool
     * @throws ModelValidateException
     */
    public function insertData(array $data)
    {
        foreach ($data as $field => $value) {
            in_array($field, $this->attributes()) ? $this->$field = $value : null;
        }
        $this->createTime = date('Y-m-d H:i:s');
        if (!$this->insert()) {
            throw new ModelValidateException(current($this->getFirstErrors()));
        }

        return true;
    }

    /**
     * @param $select
     * @param $condition
     *
     * @return array|ActiveRecord[]
     */
    public static function getRequestLogHourData($select, $condition)
    {
        return self::find()->select($select)->where($condition)->asArray()->all();
    }
}
