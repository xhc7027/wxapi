<?php

use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '请求监控报表';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="request-logger-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?= $this->render('_req-log-search'); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn',
                'header' => '行号'
            ],
            'id',
            [
                'attribute' => 'type',
                'value' => function ($model, $key, $index, $column) {
                    $type = [
                        '0' => '内部调用',
                        '1' => '微信回调',
                        '2' => '调用微信',
                    ];
                    if (isset($model->type)) {
                        return $type[$model->type];
                    }
                    return [];
                }
            ],
            'method',
            'reqTimeStr',
            'timeConsume',
            'srcIp',
            'reqUri',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) {
                        return Html::a('<span class="glyphicon glyphicon-eye-open"></span>', '/admin/req-log-view?id=' . $model->id);
                    },
                ]
            ],
        ],
    ]); ?>
</div>