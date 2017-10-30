<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\AppInfo */

$this->title = '更新公众号信息：' . $model->appId;
$this->params['breadcrumbs'][] = ['label' => '代运营公众号报表', 'url' => ['admin/app-info-index']];
$this->params['breadcrumbs'][] = ['label' => $model->appId, 'url' => '/admin/app-info-view?id=' . $model->appId];
$this->params['breadcrumbs'][] = '更新';
?>
<div class="app-info-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_app-info-form', [
        'model' => $model,
    ]) ?>

</div>