<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfo */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="app-info-form">

    <?php $form = ActiveForm::begin(['action' => ['admin/app-info-updated']]); ?>

    <?= $form->field($model, 'appId')->hiddenInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'accessToken')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'infoType')
        ->radioList(['unauthorized' => '取消授权', 'updateauthorized' => '更新授权', 'authorized' => '授权成功'])
    ?>

    <div class="form-group">
        <?= Html::submitButton(
            $model->isNewRecord ? '新增' : '修改',
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary'])
        ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>