<?php

use yii\helpers\Html;
use yii\jui\DatePicker;
use yii\widgets\ActiveForm;

?>

<div class="app-info-search">

    <?php $form = ActiveForm::begin([
        'action' => ['admin/req-log-index'],
        'method' => 'get',
    ]); ?>

    <div class="form-group field-requestlogger-reqtime">
        <label class="control-label" for="requestlogger-reqtime">请求开始时间</label>
        <?= DatePicker::widget([
            'name' => 'fromDate',
            'language' => 'zh-CN',
            'dateFormat' => 'php:Y-m-d',
            'clientOptions' => ['defaultDate' => date('Y-m-d')]
        ]);
        ?>
        <div class="help-block"></div>
    </div>

    <div class="form-group field-requestlogger-reqtime">
        <label class="control-label" for="requestlogger-reqtime">请求结束时间</label>
        <?= DatePicker::widget([
            'name' => 'toDate',
            'language' => 'zh-CN',
            'dateFormat' => 'php:Y-m-d',
            'clientOptions' => ['defaultDate' => date('Y-m-d')]
        ]);
        ?>
        <div class="help-block"></div>
    </div>

    <div class="form-group field-requestlogger-reqtime">
        <label class="control-label" for="requestlogger-reqtime">请求耗时</label>
        <input type="text" name="timeConsume">大于或等于
        <div class="help-block"></div>
    </div>

    <div class="form-group field-requestlogger-reqtime">
        <label class="control-label" for="requestlogger-reqtime">授权方AppId</label>
        <input type="text" name="appId">精确匹配
        <div class="help-block"></div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('开始查询', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('清空参数', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>