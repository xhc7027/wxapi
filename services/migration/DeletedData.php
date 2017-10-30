<?php

namespace app\services\migration;

use app\models\RequestLogger;
use yii;

/**
 * 删除过期数据<br>
 *
 * 物理删除30天以前的日志
 *
 * @package app\services\migration
 */
class DeletedData implements Builder
{
    /**
     * 对数据进行迁移
     * @return mixed
     */
    public function migration()
    {
        $formDate = date('Y-m-d', strtotime("-7 day"));
        $count = RequestLogger::find()->where(['<', 'reqTime', $formDate . ' 00:00:00'])->count();
        Yii::info($formDate . ' 找到要被删除的记录 ' . $count . ' 条。');
        RequestLogger::deleteAll(['<', 'reqTime', $formDate . ' 00:00:00']);
    }
}