<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfoSearch */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="app-info-search">

    <?php $form = ActiveForm::begin([
        'action' => ['admin/app-info-index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'appId')->label('AppID') ?>
    <?= $form->field($model, 'nickName')->label('昵称(支持模糊查询)') ?>

    <div class="form-group">
        <?= Html::submitButton('开始查询', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('清空参数', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>